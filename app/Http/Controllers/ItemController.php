<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * 1. Menampilkan Katalog Visual Dual Feed & Pencarian Hashtag (FR-02, FR-03).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $allowedScopes = $user->isSecurity() ? ['feed', 'mine', 'archive'] : ['feed', 'mine'];
        $scope = in_array($request->get('scope'), $allowedScopes, true)
            ? $request->get('scope')
            : 'feed';

        $type = in_array($request->get('type'), ['found', 'lost'], true)
            ? $request->get('type')
            : 'found';
        $q = trim((string) $request->get('q', ''));
        $categoryId = $request->integer('category') ?: null;
        $building = trim((string) $request->get('building', ''));
        $sort = in_array($request->get('sort'), ['latest', 'event_desc', 'most_active'], true)
            ? $request->get('sort')
            : 'latest';

        $foundStatuses = ['open', 'claimed'];
        $lostStatuses = ['open'];

        $baseActiveQuery = fn (string $itemType) => Item::where('is_hidden', false)
            ->whereHas('user', fn ($userQuery) => $userQuery->where('is_blocked', false))
            ->where('type', $itemType)
            ->whereIn('status', $itemType === 'found' ? $foundStatuses : $lostStatuses);

        if ($scope === 'mine') {
            $query = Item::where('user_id', $user->id);
        } elseif ($scope === 'archive') {
            $query = Item::whereIn('status', ['returned', 'expired']);
        } else {
            $query = $baseActiveQuery($type);
        }

        $query->with(['category', 'tags', 'user'])
            ->withCount(['messages', 'claims']);

        match ($sort) {
            'event_desc' => $query->orderByDesc('event_date')->latest(),
            'most_active' => $query->orderByDesc('messages_count')->orderByDesc('claims_count')->latest(),
            default => $query->latest(),
        };

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($building !== '') {
            $query->where('building', $building);
        }

        if ($q !== '') {
            $cleanedTag = strtolower(ltrim($q, '#'));
            $query->where(function ($sub) use ($q, $cleanedTag) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhere('building', 'like', '%'.$q.'%')
                    ->orWhere('custody_location', 'like', '%'.$q.'%')
                    ->orWhere('custom_category', 'like', '%'.$q.'%')
                    ->orWhereHas('tags', function ($tagQuery) use ($cleanedTag) {
                        $tagQuery->where('name', 'like', '%'.$cleanedTag.'%');
                    });
            });
        }

        $items = $query->paginate(12)->withQueryString();

        $totalFound = $baseActiveQuery('found')->count();
        $totalLost = $baseActiveQuery('lost')->count();
        $totalMine = Item::where('user_id', $user->id)->count();
        $totalArchive = $user->isSecurity() ? Item::whereIn('status', ['returned', 'expired'])->count() : 0;

        $categories = Category::orderedForDropdown();
        $buildings = config('lostracer.buildings');
        $popularTags = Tag::withCount('items')
            ->orderByDesc('items_count')
            ->limit(6)
            ->get();

        $extendedShelfDueCount = 0;
        if ($user->isSecurity()) {
            $extendedShelfDueCount = Item::where('type', 'found')
                ->whereIn('status', ['open', 'claimed'])
                ->get()
                ->filter(fn (Item $item) => $item->isDueForExtendedShelf())
                ->count();
        }

        return view('dashboard', compact(
            'items',
            'scope',
            'type',
            'q',
            'categoryId',
            'building',
            'sort',
            'categories',
            'buildings',
            'popularTags',
            'totalFound',
            'totalLost',
            'totalMine',
            'totalArchive',
            'extendedShelfDueCount'
        ));
    }

    /**
     * 2. Menampilkan Form Buat Laporan Barang (FR-04).
     */
    public function create()
    {
        $categories = Category::orderedForDropdown();
        $buildings = config('lostracer.buildings');
        $depositLocations = Auth::user()->isSecurity()
            ? config('lostracer.custody_locations')
            : config('lostracer.deposit_locations');

        return view('items.create', compact('categories', 'buildings', 'depositLocations'));
    }

    /**
     * 3. Menyimpan Laporan Barang & Upload Foto (FR-04, AC-06, CUS-02, PRV-05).
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $allowedTypes = $user->isSecurity() ? ['found'] : ['lost', 'found'];
        $allowedLocations = $user->isSecurity()
            ? config('lostracer.custody_locations')
            : config('lostracer.deposit_locations');
        $isOthersCategory = $this->isOthersCategoryInput($request->input('category_id'));

        $validated = $request->validate([
            'type' => ['required', Rule::in($allowedTypes)],
            ...$this->categoryValidationRules($isOthersCategory),
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'building' => ['required', Rule::in(config('lostracer.buildings'))],
            'floor_detail' => ['required', 'string', 'max:100'],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'photo' => [
                Rule::requiredIf(fn () => $request->input('type') === 'found'),
                'nullable',
                'image',
                'max:2048',
            ],
            'custody_location' => [
                Rule::requiredIf(fn () => $request->input('type') === 'found'),
                'nullable',
                Rule::in($allowedLocations),
            ],
            'deposit_note' => [
                Rule::requiredIf(fn () => $request->input('type') === 'found'),
                'nullable',
                'string',
                'max:500',
            ],
            'hidden_detail' => ['nullable', 'string', 'max:500'],
            'tags' => ['required', 'string'],
        ], [
            'photo.required' => 'Found posts require an item photo.',
            'custody_location.required' => 'Custody location (security post / lobby) is required for found items.',
            'deposit_note.required' => 'Deposit note (officer name / shift) is required for found items.',
            ...$this->categoryValidationMessages(),
        ]);

        $cleanedTags = $this->parseTags($validated['tags']);
        if (count($cleanedTags) < 1 || count($cleanedTags) > 5) {
            return back()
                ->withErrors(['tags' => 'Please enter between 1 and 5 hashtags (comma-separated).'])
                ->withInput();
        }

        $isSensitive = $request->boolean('is_sensitive');
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $disk = $isSensitive ? 'local' : 'public';
            $folder = $isSensitive ? 'items/sensitive' : 'items';
            $photoPath = $request->file('photo')->store($folder, $disk);
        }

        $isFound = $validated['type'] === 'found';
        $shelfMoveMonths = (int) config('lostracer.retention.shelf_move_months', 3);
        $expirationMonths = (int) config('lostracer.retention.expiration_months', 6);
        $categoryPayload = $this->resolveCategoryPayload($isOthersCategory, $validated);

        $item = DB::transaction(function () use ($user, $validated, $categoryPayload, $photoPath, $isSensitive, $isFound, $shelfMoveMonths, $expirationMonths, $cleanedTags) {
            $item = Item::create([
                'user_id' => $user->id,
                'category_id' => $categoryPayload['category_id'],
                'custom_category' => $categoryPayload['custom_category'],
                'type' => $validated['type'],
                'status' => 'open',
                'title' => $validated['title'],
                'description' => $validated['description'],
                'building' => $validated['building'],
                'floor_detail' => $validated['floor_detail'],
                'event_date' => $validated['event_date'],
                'photo_path' => $photoPath,
                'is_sensitive' => $isSensitive,
                'custody_location' => $isFound ? $validated['custody_location'] : null,
                'deposit_note' => $isFound ? $validated['deposit_note'] : null,
                'hidden_detail' => $isFound ? ($validated['hidden_detail'] ?? null) : null,
                'move_due_at' => now()->addMonths($shelfMoveMonths),
                'expires_at' => now()->addMonths($expirationMonths),
                'is_hidden' => false,
            ]);

            $this->syncTags($item, $cleanedTags);

            return $item;
        });

        return redirect()->route('items.show', $item)
            ->with('success', 'Post published successfully!');
    }

    /**
     * 4. Menampilkan Detail Laporan, Klaim, Chat, dan Panel Petugas (Module 1).
     */
    public function show(Item $item)
    {
        $user = Auth::user();

        if (($item->is_hidden || $item->user->is_blocked) && ! $user->isSecurity() && $item->user_id !== $user->id) {
            abort(404);
        }

        $item->load(['category', 'tags', 'user', 'claims.user', 'claims.verifier', 'auditLogs.user']);

        $userClaims = $item->claims
            ->where('user_id', $user->id)
            ->sortByDesc('id');

        $rejectedAttempts = $userClaims->where('status', 'rejected')->count();
        $maxAttempts = (int) config('lostracer.retention.max_claim_attempts', 2);

        $hasActiveUserClaim = $userClaims->whereIn('status', ['pending', 'approved'])->isNotEmpty();

        $canSubmitClaim = $user->isUser()
            && $item->user_id !== $user->id
            && $item->isClaimable()
            && ! $hasActiveUserClaim
            && $rejectedAttempts < $maxAttempts;

        $canEditItem = ! $item->isTerminal() && (
            $user->isSecurity() || ($item->user_id === $user->id && ! $item->hasAnyClaims())
        );

        $canTakeDownItem = $user->isUser()
            && $item->user_id === $user->id
            && ! $item->hasAnyClaims()
            && ! $item->isTerminal();

        $canCloseLostReport = $item->type === 'lost'
            && $item->status === 'open'
            && $item->user_id === $user->id;

        // Pesan in-app dipisahkan per percakapan 1-on-1 antara pengguna aktif dan masing-masing lawan bicara (ROL-03 melarang it_admin)
        $messages = collect();
        $chatRecipients = collect();
        $existingChatPeers = collect();
        $activeChatPeer = null;

        if (! $user->isItAdmin()) {
            $userItemMessages = $item->messages()
                ->with(['sender', 'receiver'])
                ->where(function ($q) use ($user) {
                    $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
                })
                ->orderBy('created_at')
                ->get();

            $existingPeerIds = $userItemMessages
                ->map(fn ($msg) => $msg->sender_id === $user->id ? $msg->receiver_id : $msg->sender_id)
                ->unique()
                ->values();

            $existingChatPeers = User::whereIn('id', $existingPeerIds)
                ->where('is_blocked', false)
                ->where('role', '!=', 'it_admin')
                ->get();

            $participantIds = collect([$item->user_id])
                ->merge($existingPeerIds)
                ->merge(User::where('role', 'security')->pluck('id'))
                ->unique()
                ->reject(fn ($id) => $id === $user->id);

            $chatRecipients = User::whereIn('id', $participantIds)
                ->where('is_blocked', false)
                ->where('role', '!=', 'it_admin')
                ->orderBy('name')
                ->get();

            $requestedPeerId = request()->integer('chat_peer') ?: null;
            if ($requestedPeerId) {
                $activeChatPeer = $chatRecipients->firstWhere('id', $requestedPeerId);
            }

            if (! $activeChatPeer) {
                $latestMsg = $userItemMessages->last();
                if ($latestMsg) {
                    $latestPeerId = $latestMsg->sender_id === $user->id ? $latestMsg->receiver_id : $latestMsg->sender_id;
                    $activeChatPeer = $chatRecipients->firstWhere('id', $latestPeerId);
                }
            }

            if (! $activeChatPeer) {
                $activeChatPeer = $chatRecipients->first();
            }

            if ($activeChatPeer) {
                $messages = $userItemMessages->filter(function ($msg) use ($user, $activeChatPeer) {
                    return ($msg->sender_id === $user->id && $msg->receiver_id === $activeChatPeer->id)
                        || ($msg->sender_id === $activeChatPeer->id && $msg->receiver_id === $user->id);
                })->values();
            }
        }

        return view('items.show', compact(
            'item',
            'userClaims',
            'rejectedAttempts',
            'maxAttempts',
            'hasActiveUserClaim',
            'canSubmitClaim',
            'canEditItem',
            'canTakeDownItem',
            'canCloseLostReport',
            'messages',
            'chatRecipients',
            'existingChatPeers',
            'activeChatPeer'
        ));
    }

    /**
     * 5. Menyajikan Foto Barang dengan Perlindungan Privasi Barang Sensitif (PRV-05).
     */
    public function photo(Item $item)
    {
        if (! $item->photo_path) {
            abort(404);
        }

        if (! $item->is_sensitive) {
            if (! Storage::disk('public')->exists($item->photo_path)) {
                abort(404);
            }

            return Storage::disk('public')->response($item->photo_path);
        }

        // Barang sensitif disimpan di disk privat 'local'
        $disk = Storage::disk('local')->exists($item->photo_path) ? 'local' : 'public';
        if (! Storage::disk($disk)->exists($item->photo_path)) {
            abort(404);
        }

        // Hanya petugas security yang berhak melihat foto asli tanpa sensor
        if (Auth::user()->isSecurity()) {
            return Storage::disk($disk)->response($item->photo_path);
        }

        $rawBytes = Storage::disk($disk)->get($item->photo_path);
        $blurred = $this->createBlurredDerivative($rawBytes);

        if ($blurred !== null) {
            return response($blurred, 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        }

        // Fallback jika ekstensi PHP GD tidak aktif di lingkungan lokal (PRV-05)
        return response($this->lockedPrivacyPlaceholderSvg(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * 6. Menampilkan Form Edit Laporan (REP-04).
     */
    public function edit(Item $item)
    {
        $this->authorizeItemEdit($item);

        $categories = Category::orderedForDropdown();
        $buildings = config('lostracer.buildings');
        $tagsString = $item->tags->pluck('name')->implode(', ');

        return view('items.edit', compact('item', 'categories', 'buildings', 'tagsString'));
    }

    /**
     * 7. Memperbarui Laporan Barang (REP-04, REP-08).
     */
    public function update(Request $request, Item $item)
    {
        $this->authorizeItemEdit($item);
        $user = Auth::user();
        $isOthersCategory = $this->isOthersCategoryInput($request->input('category_id'));

        $validated = $request->validate([
            ...$this->categoryValidationRules($isOthersCategory),
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'building' => ['required', Rule::in(config('lostracer.buildings'))],
            'floor_detail' => ['required', 'string', 'max:100'],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'deposit_note' => ['nullable', 'string', 'max:500'],
            'hidden_detail' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'tags' => ['required', 'string'],
        ], $this->categoryValidationMessages());

        $cleanedTags = $this->parseTags($validated['tags']);
        if (count($cleanedTags) < 1 || count($cleanedTags) > 5) {
            return back()
                ->withErrors(['tags' => 'Please enter between 1 and 5 hashtags (comma-separated).'])
                ->withInput();
        }

        $oldSensitive = $item->is_sensitive;
        $newSensitive = $request->boolean('is_sensitive');
        $photoPath = $item->photo_path;

        if ($request->hasFile('photo')) {
            if ($photoPath) {
                $oldDisk = $oldSensitive ? 'local' : 'public';
                if (Storage::disk($oldDisk)->exists($photoPath)) {
                    Storage::disk($oldDisk)->delete($photoPath);
                }
            }
            $newDisk = $newSensitive ? 'local' : 'public';
            $newFolder = $newSensitive ? 'items/sensitive' : 'items';
            $photoPath = $request->file('photo')->store($newFolder, $newDisk);
        } elseif ($photoPath && $oldSensitive !== $newSensitive) {
            $photoPath = $this->movePhotoPrivacyDisk($photoPath, $oldSensitive, $newSensitive);
        }

        $categoryPayload = $this->resolveCategoryPayload($isOthersCategory, $validated);

        DB::transaction(function () use ($item, $validated, $categoryPayload, $newSensitive, $photoPath, $cleanedTags, $user, $oldSensitive) {
            $item->update([
                'category_id' => $categoryPayload['category_id'],
                'custom_category' => $categoryPayload['custom_category'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'building' => $validated['building'],
                'floor_detail' => $validated['floor_detail'],
                'event_date' => $validated['event_date'],
                'deposit_note' => $item->type === 'found' ? ($validated['deposit_note'] ?? $item->deposit_note) : null,
                'hidden_detail' => $item->type === 'found' ? ($validated['hidden_detail'] ?? $item->hidden_detail) : null,
                'is_sensitive' => $newSensitive,
                'photo_path' => $photoPath,
            ]);

            $this->syncTags($item, $cleanedTags);

            if ($user->isSecurity()) {
                $note = $oldSensitive !== $newSensitive
                    ? 'Petugas memperbarui laporan dan mengubah status barang sensitif menjadi '.($newSensitive ? 'Ya' : 'Tidak').'.'
                    : 'Petugas mengoreksi data laporan barang.';
                AuditLog::record('ITEM_EDITED', $item->id, null, $note);
            }
        });

        return redirect()->route('items.show', $item)
            ->with('success', 'Post updated successfully.');
    }

    /**
     * 8. Close Own Lost Post (STA-04, REP-06, FR-12).
     */
    public function close(Item $item)
    {
        if ($item->type !== 'lost' || $item->user_id !== Auth::id()) {
            abort(403, 'Only the lost-post owner may close this post.');
        }

        if ($item->status !== 'open') {
            return back()->with('error', 'This post is already in a terminal status.');
        }

        $item->update(['status' => 'returned']);

        return redirect()->route('items.show', $item)
            ->with('success', 'Your lost post has been marked as returned.');
    }

    /**
     * 8b. Take Down / Cancel Own Post Before Any Claim Exists (User Role).
     */
    public function destroy(Item $item)
    {
        $user = Auth::user();

        if (! $user->isUser() || $item->user_id !== $user->id) {
            abort(403, 'You may only cancel and take down your own posts.');
        }

        if ($item->isTerminal() || $item->hasAnyClaims()) {
            abort(403, 'Posts cannot be taken down after a claim has been submitted or once closed.');
        }

        DB::transaction(function () use ($item, $user) {
            $summary = sprintf(
                'Post #%d ("%s", type: %s) canceled and taken down by owner (%s).',
                $item->id,
                $item->title,
                $item->type,
                $user->email
            );

            AuditLog::record('POST_DELETED', $item->id, null, $summary);

            if ($item->photo_path) {
                $disk = $item->is_sensitive ? 'local' : 'public';
                if (Storage::disk($disk)->exists($item->photo_path)) {
                    Storage::disk($disk)->delete($item->photo_path);
                }
            }

            $item->delete();
        });

        return redirect()->route('dashboard')
            ->with('success', 'Your post has been canceled and taken down.');
    }

    /**
     * 9. Form Pemindahan Lokasi Penyimpanan & Koreksi Status oleh Security (FR-11, AC-02).
     */
    public function locationForm(Item $item)
    {
        if ($item->type !== 'found') {
            abort(404, 'Custody relocation is only available for found posts.');
        }

        $custodyLocations = config('lostracer.custody_locations');

        return view('items.location', compact('item', 'custodyLocations'));
    }

    /**
     * 10. Menyimpan Pemindahan Lokasi Penyimpanan & Log Audit (FR-11, AC-02, CUS-04, CUS-08).
     */
    public function updateLocation(Request $request, Item $item)
    {
        if ($item->type !== 'found') {
            abort(404);
        }

        if ($item->isTerminal()) {
            return back()->with('error', 'Items in a terminal status cannot be relocated.');
        }

        $validated = $request->validate([
            'custody_location' => ['required', Rule::in(config('lostracer.custody_locations'))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $oldLocation = $item->custody_location;
        $oldSensitive = $item->is_sensitive;
        $oldHidden = $item->is_hidden;

        $newSensitive = $request->boolean('is_sensitive');
        $newHidden = $request->boolean('is_hidden');

        $photoPath = $item->photo_path;
        if ($photoPath && $oldSensitive !== $newSensitive) {
            $photoPath = $this->movePhotoPrivacyDisk($photoPath, $oldSensitive, $newSensitive);
        }

        DB::transaction(function () use (
            $item,
            $validated,
            $oldLocation,
            $oldSensitive,
            $newSensitive,
            $oldHidden,
            $newHidden,
            $photoPath
        ) {
            $item->update([
                'custody_location' => $validated['custody_location'],
                'is_sensitive' => $newSensitive,
                'is_hidden' => $newHidden,
                'photo_path' => $photoPath,
            ]);

            if ($oldLocation !== $validated['custody_location']) {
                $moveNote = sprintf(
                    'Dipindahkan dari "%s" ke "%s". %s',
                    $oldLocation ?? '-',
                    $validated['custody_location'],
                    $validated['notes'] ?? ''
                );
                AuditLog::record('ITEM_MOVED', $item->id, null, trim($moveNote));
            }

            if ($oldSensitive !== $newSensitive || $oldHidden !== $newHidden) {
                $changes = [];
                if ($oldSensitive !== $newSensitive) {
                    $changes[] = 'sensor sensitif='.($newSensitive ? 'aktif' : 'nonaktif');
                }
                if ($oldHidden !== $newHidden) {
                    $changes[] = 'visibilitas='.($newHidden ? 'disembunyikan' : 'tampil');
                }
                $editNote = 'Koreksi laporan oleh petugas ('.implode(', ', $changes).'). '.($validated['notes'] ?? '');
                AuditLog::record('ITEM_EDITED', $item->id, null, trim($editNote));
            }
        });

        return redirect()->route('items.show', $item)
            ->with('success', 'Custody location and post status updated.');
    }

    private function authorizeItemEdit(Item $item): void
    {
        $user = Auth::user();

        if ($item->isTerminal()) {
            abort(403, 'Posts in a terminal status cannot be edited.');
        }

        if ($user->isSecurity()) {
            return;
        }

        if ($item->user_id !== $user->id) {
            abort(403, 'You may only edit your own posts.');
        }

        if ($item->hasAnyClaims()) {
            abort(403, 'Posts cannot be edited once a claim has been submitted.');
        }
    }

    private function parseTags(string $rawTags): array
    {
        $parts = explode(',', $rawTags);
        $cleaned = [];

        foreach ($parts as $part) {
            $tag = strtolower(trim(ltrim($part, '# ')));
            if ($tag !== '') {
                $cleaned[] = $tag;
            }
        }

        return array_values(array_unique($cleaned));
    }

    private function syncTags(Item $item, array $tagNames): void
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Tag::firstOrCreate(['name' => $name]);
            $tagIds[] = $tag->id;
        }

        $item->tags()->sync(array_unique($tagIds));
    }

    /**
     * Memindahkan berkas foto antara disk public dan disk privat local saat flag is_sensitive berubah.
     */
    private function movePhotoPrivacyDisk(string $path, bool $wasSensitive, bool $nowSensitive): string
    {
        $sourceDisk = $wasSensitive ? 'local' : 'public';
        $targetDisk = $nowSensitive ? 'local' : 'public';

        if (! Storage::disk($sourceDisk)->exists($path)) {
            return $path;
        }

        $filename = basename($path);
        $targetPath = ($nowSensitive ? 'items/sensitive/' : 'items/').$filename;

        Storage::disk($targetDisk)->put($targetPath, Storage::disk($sourceDisk)->get($path));
        Storage::disk($sourceDisk)->delete($path);

        return $targetPath;
    }

    /**
     * Membuat versi gambar ter-blur menggunakan PHP GD agar NIK/NIM tidak terbaca publik (PRV-05).
     */
    private function createBlurredDerivative(string $rawBytes): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $source = @imagecreatefromstring($rawBytes);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // Downscale lalu blur berulang agar konten sensitif benar-benar tersamar
        $smallW = max(24, (int) ($width / 12));
        $smallH = max(24, (int) ($height / 12));
        $tiny = imagecreatetruecolor($smallW, $smallH);
        imagecopyresampled($tiny, $source, 0, 0, 0, 0, $smallW, $smallH, $width, $height);

        for ($i = 0; $i < 6; $i++) {
            imagefilter($tiny, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $output = imagecreatetruecolor($width, $height);
        imagecopyresampled($output, $tiny, 0, 0, 0, 0, $width, $height, $smallW, $smallH);

        ob_start();
        imagejpeg($output, null, 75);
        $jpegData = ob_get_clean();

        imagedestroy($source);
        imagedestroy($tiny);
        imagedestroy($output);

        return $jpegData ?: null;
    }

    private function lockedPrivacyPlaceholderSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">
  <rect width="640" height="360" fill="#EFF6FF"/>
  <rect x="24" y="24" width="592" height="312" rx="16" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" stroke-dasharray="8 6"/>
  <circle cx="320" cy="145" r="34" fill="#1E3A8A"/>
  <rect x="307" y="138" width="26" height="20" rx="4" fill="#FFFFFF"/>
  <path d="M312 138 v-6 a8 8 0 0 1 16 0 v6" fill="none" stroke="#FFFFFF" stroke-width="3"/>
  <text x="320" y="212" text-anchor="middle" font-family="sans-serif" font-size="18" font-weight="700" fill="#1E3A8A">Foto Dokumen Sensitif Terlindungi</text>
  <text x="320" y="240" text-anchor="middle" font-family="sans-serif" font-size="13" fill="#64748B">Hanya petugas keamanan (security) yang dapat melihat foto asli.</text>
</svg>
SVG;
    }

    private function isOthersCategoryInput(mixed $rawCategory): bool
    {
        if (is_string($rawCategory) && strtolower(trim($rawCategory)) === 'others') {
            return true;
        }

        if (is_numeric($rawCategory)) {
            $category = Category::find((int) $rawCategory);

            return $category !== null && $category->isOthers();
        }

        return false;
    }

    private function categoryValidationRules(bool $isOthersCategory): array
    {
        return [
            'category_id' => $isOthersCategory ? ['required'] : ['required', 'exists:categories,id'],
            'custom_category' => [
                Rule::requiredIf($isOthersCategory),
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    private function categoryValidationMessages(): array
    {
        return [
            'custom_category.required' => 'Please specify the category name when selecting Others.',
        ];
    }

    private function resolveCategoryPayload(bool $isOthersCategory, array $validated): array
    {
        if ($isOthersCategory) {
            $othersCategory = Category::whereRaw('LOWER(name) = ?', ['others'])->first()
                ?? Category::create(['name' => 'Others']);

            return [
                'category_id' => $othersCategory->id,
                'custom_category' => trim((string) $validated['custom_category']),
            ];
        }

        return [
            'category_id' => (int) $validated['category_id'],
            'custom_category' => null,
        ];
    }
}
