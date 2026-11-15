<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    //fetch all chat records involved
    public function index()
    {
        // Get distinct item IDs where the logged-in user has sent or received messages
        $itemIds = Message::where('sender_id', auth()->id())
            ->orWhere('receiver_id', auth()->id())
            ->pluck('item_id')
            ->unique();

        // Fetch the items along with the latest message for each conversation
        $conversations = Item::whereIn('id', $itemIds)
            ->with(['messages' => function ($query) {
                $query->latest();
            }, 'user'])
            ->get();

        return view('messages.index', compact('conversations'));
    }

    // Render the chat view for a specific item
    public function show(Item $item)
    {
        // Fetch all messages for this item involving the current user
        $messages = Message::where('item_id', $item->id)
            ->where(function ($query) {
                $query->where('sender_id', auth()->id())
                      ->orWhere('receiver_id', auth()->id());
            })
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark incoming messages as read
        Message::where('item_id', $item->id)
            ->where('receiver_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return view('messages.show', compact('item', 'messages'));
    }

    // Handle sending a new message
    public function store(Request $request, Item $item)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // If logged-in user is item owner, send to the last person who messaged them;
        // otherwise, send to item owner.
        $receiverId = $item->user_id;
        if (auth()->id() === $item->user_id) {
            $lastMessage = Message::where('item_id', $item->id)
                ->where('sender_id', '!=', auth()->id())
                ->latest()
                ->first();
                
            if ($lastMessage) {
                $receiverId = $lastMessage->sender_id;
            }
        }

        Message::create([
            'item_id'     => $item->id,
            'sender_id'   => auth()->id(),
            'receiver_id' => $receiverId,
            'message'     => $request->message,
            'is_read'     => false,
        ]);

        return redirect()->back();
    }
}