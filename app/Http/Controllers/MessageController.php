<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Display the 1-on-1 In-App Chat workspace separated per (item, peer) thread (FR-05, PRV-01, PRV-02).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedItemId = $request->integer('item') ?: null;
        $selectedPeerId = $request->integer('peer') ?: null;

        // Fetch all messages involving the current user ordered newest-first
        $userMessages = Message::with(['item.category', 'item.user', 'sender', 'receiver'])
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
            })
            ->latest()
            ->get();

        // Build unique 1-on-1 threads keyed by "{item_id}:{peer_id}"
        $threads = collect();
        foreach ($userMessages as $msg) {
            if (! $msg->item || ($msg->item->is_hidden && ! $user->isSecurity())) {
                continue;
            }

            $peer = $msg->sender_id === $user->id ? $msg->receiver : $msg->sender;
            if (! $peer) {
                continue;
            }

            $key = $msg->item_id.':'.$peer->id;
            if (! $threads->has($key)) {
                $unreadCount = $userMessages
                    ->where('item_id', $msg->item_id)
                    ->where('sender_id', $peer->id)
                    ->where('receiver_id', $user->id)
                    ->where('is_read', false)
                    ->count();

                $threads->put($key, (object) [
                    'key' => $key,
                    'item' => $msg->item,
                    'peer' => $peer,
                    'last_message' => $msg,
                    'unread_count' => $unreadCount,
                ]);
            }
        }

        // If navigated directly to a post (?item=X) without an existing thread yet, open a draft thread
        if ($selectedItemId) {
            $requestedItem = Item::with(['category', 'user'])->find($selectedItemId);
            if ($requestedItem && (! $requestedItem->is_hidden || $user->isSecurity())) {
                $matchingThread = $selectedPeerId
                    ? $threads->get($selectedItemId.':'.$selectedPeerId)
                    : $threads->first(fn ($t) => $t->item->id === $selectedItemId);

                if (! $matchingThread) {
                    $draftPeer = $selectedPeerId
                        ? User::where('id', $selectedPeerId)->where('is_blocked', false)->where('role', '!=', 'it_admin')->first()
                        : null;

                    if (! $draftPeer) {
                        $draftPeer = $requestedItem->user_id !== $user->id
                            ? $requestedItem->user
                            : User::where('role', 'security')->where('id', '!=', $user->id)->first();
                    }

                    if ($draftPeer && $draftPeer->id !== $user->id && ! $draftPeer->is_blocked && ! $draftPeer->isItAdmin()) {
                        $key = $requestedItem->id.':'.$draftPeer->id;
                        $threads->prepend((object) [
                            'key' => $key,
                            'item' => $requestedItem,
                            'peer' => $draftPeer,
                            'last_message' => null,
                            'unread_count' => 0,
                        ], $key);
                    }
                }
            }
        }

        $activeThread = null;
        if ($selectedItemId && $selectedPeerId) {
            $activeThread = $threads->get($selectedItemId.':'.$selectedPeerId);
        } elseif ($selectedItemId) {
            $activeThread = $threads->first(fn ($t) => $t->item->id === $selectedItemId);
        } else {
            $activeThread = $threads->first();
        }

        $activeItem = $activeThread?->item;
        $activePeer = $activeThread?->peer;
        $messages = collect();
        $newChatRecipients = collect();

        if ($activeItem && $activePeer) {
            // Load strictly the 1-on-1 conversation between current user and active peer on this item
            $messages = Message::with(['sender', 'receiver'])
                ->where('item_id', $activeItem->id)
                ->where(function ($q) use ($user, $activePeer) {
                    $q->where(function ($pair) use ($user, $activePeer) {
                        $pair->where('sender_id', $user->id)->where('receiver_id', $activePeer->id);
                    })->orWhere(function ($pair) use ($user, $activePeer) {
                        $pair->where('sender_id', $activePeer->id)->where('receiver_id', $user->id);
                    });
                })
                ->orderBy('created_at')
                ->get();

            // Mark only this specific peer's incoming messages on this item as read
            Message::where('item_id', $activeItem->id)
                ->where('sender_id', $activePeer->id)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            // Update unread badge count in memory after marking read
            $activeThread->unread_count = 0;

            // Optional quick-switch recipients for starting another 1-on-1 chat on the same item
            $newChatRecipients = User::where('is_blocked', false)
                ->where('role', '!=', 'it_admin')
                ->where('id', '!=', $user->id)
                ->whereIn('id', collect([$activeItem->user_id])->merge(User::where('role', 'security')->pluck('id'))->unique())
                ->orderBy('name')
                ->get();
        }

        return view('messages.index', compact(
            'threads',
            'activeThread',
            'activeItem',
            'activePeer',
            'messages',
            'newChatRecipients'
        ));
    }

    /**
     * Fetch new messages in a 1-on-1 thread as JSON for live chat updates.
     */
    public function thread(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'item' => ['required', 'integer', 'exists:items,id'],
            'peer' => ['required', 'integer', 'exists:users,id'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = Item::findOrFail($validated['item']);
        if ($item->is_hidden && ! $user->isSecurity()) {
            abort(404);
        }

        $peer = User::findOrFail($validated['peer']);
        if ($this->isInvalidPeer($user, $peer, allowBlocked: true)) {
            return response()->json(['message' => 'Invalid message recipient.'], 422);
        }

        $afterId = (int) ($validated['after_id'] ?? 0);

        $newMessages = Message::with(['sender'])
            ->where('item_id', $item->id)
            ->where(function ($q) use ($user, $peer) {
                $q->where(function ($pair) use ($user, $peer) {
                    $pair->where('sender_id', $user->id)->where('receiver_id', $peer->id);
                })->orWhere(function ($pair) use ($user, $peer) {
                    $pair->where('sender_id', $peer->id)->where('receiver_id', $user->id);
                });
            })
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->get();

        // Mark incoming unread messages in this viewed thread as read
        Message::where('item_id', $item->id)
            ->where('sender_id', $peer->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $totalUnread = Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'messages' => $newMessages->map(fn (Message $msg) => $this->serializeMessage($msg, $user))->values(),
            'unread_total' => $totalUnread,
            'is_terminal' => $item->isTerminal(),
            'item_status' => $item->status,
        ]);
    }

    /**
     * Send an encrypted coordination message on an item post (FR-05, PRV-01, PRV-02, CLM-10).
     */
    public function store(Request $request, Item $item)
    {
        $user = Auth::user();

        if ($item->is_hidden && ! $user->isSecurity()) {
            abort(404);
        }

        // HND-04: Chat threads become read-only once the item is returned or expired
        if ($item->isTerminal()) {
            $msg = 'This chat is closed because the post has reached a final status.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        $validated = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'message_body' => ['required', 'string', 'max:1000'],
        ], [
            'receiver_id.required' => 'Please select a recipient first.',
            'message_body.required' => 'Message body cannot be empty.',
        ]);

        $receiver = User::findOrFail($validated['receiver_id']);
        if ($this->isInvalidPeer($user, $receiver)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Invalid message recipient.'], 422);
            }

            return back()->with('error', 'Invalid message recipient.');
        }

        $message = Message::create([
            'item_id' => $item->id,
            'sender_id' => $user->id,
            'receiver_id' => $receiver->id,
            'message_body' => $validated['message_body'], // Encrypted automatically via Eloquent cast
            'is_read' => false,
        ]);

        $message->setRelation('sender', $user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->serializeMessage($message, $user),
            ], 201);
        }

        if ($request->input('redirect_to') === 'messages') {
            return redirect()->route('messages.index', ['item' => $item->id, 'peer' => $receiver->id])
                ->with('success', 'Message sent securely.');
        }

        return redirect()->route('items.show', ['item' => $item, 'chat_peer' => $receiver->id])
            ->with('success', 'Message sent securely.');
    }

    private function isInvalidPeer(User $user, User $peer, bool $allowBlocked = false): bool
    {
        return $peer->id === $user->id
            || $peer->isItAdmin()
            || (! $allowBlocked && $peer->is_blocked);
    }

    /**
     * Format a decrypted message for live chat JSON responses.
     *
     * @return array<string, mixed>
     */
    private function serializeMessage(Message $message, User $viewer): array
    {
        $isMine = $message->sender_id === $viewer->id;

        return [
            'id' => $message->id,
            'is_mine' => $isMine,
            'sender_name' => $isMine ? 'You' : ($message->sender?->name ?? 'User'),
            'body' => $message->message_body,
            'timestamp' => $message->created_at->format('d M, H:i'),
        ];
    }
}
