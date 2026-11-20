@extends('layouts.app')

@section('title', 'Chat')

@section('konten')
<div style="margin-bottom: 18px;">
    <h1 class="page-title">Chat</h1>
    <p style="margin: 4px 0 0; color: var(--text-muted);">
        Direct messages for item posts and campus security inquiries.
    </p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 18px; align-items: stretch;">
    {{-- Left Column: 1-on-1 Direct Message Threads (Item + Peer) --}}
    <div class="card-surface" style="padding: 14px; max-width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 6px 12px; border-bottom: 1px solid var(--border-color); margin-bottom: 10px;">
            <strong style="font-size: 13.5px; color: var(--primary-navy);">Conversations ({{ $threads->count() }})</strong>
            <a href="{{ route('dashboard') }}" class="caption" style="text-decoration: none; color: var(--primary-navy); font-weight: 600;">Browse Posts &rarr;</a>
        </div>

        @if($threads->isNotEmpty())
            <div style="margin-bottom: 10px;" data-search-clearable>
                <div class="input-with-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="search" data-thread-filter data-shortcut-search placeholder="Filter conversations..." style="min-height: 36px; font-size: 13px; padding-right: 32px;">
                    <button type="button" class="input-clear-btn" data-clear-input aria-label="Clear conversation filter" hidden>&times;</button>
                </div>
            </div>
        @endif

        <div data-thread-list style="display: flex; flex-direction: column; gap: 6px; max-height: 460px; overflow-y: auto;">
            @forelse($threads as $thread)
                @php
                    $isActive = $activeThread && $activeThread->key === $thread->key;
                    $peerInitials = collect(explode(' ', trim($thread->peer->name)))
                        ->filter()
                        ->take(2)
                        ->map(fn ($p) => strtoupper(mb_substr($p, 0, 1)))
                        ->implode('');
                @endphp
                <a href="{{ route('messages.index', ['item' => $thread->item->id, 'peer' => $thread->peer->id]) }}"
                   class="thread-item-link {{ $isActive ? 'is-active' : '' }}"
                   data-thread-item
                   data-thread-key="{{ $thread->key }}"
                   data-thread-search="{{ $thread->peer->name }} {{ $thread->item->title }}">
                    <span class="ig-avatar" style="width: 38px; height: 38px;">{{ $peerInitials ?: 'U' }}</span>
                    <div style="min-width: 0; flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                            <span style="font-weight: 700; font-size: 13.5px; color: var(--text-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $thread->peer->name }}
                            </span>
                            @if($thread->unread_count > 0)
                                <span class="badge badge-danger">{{ $thread->unread_count }}</span>
                            @endif
                        </div>
                        <div class="caption" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; color: var(--primary-navy);">
                            {{ $thread->item->title }}
                        </div>
                        <div class="caption" style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                            <span class="badge {{ $thread->item->type === 'found' ? 'badge-found-open' : 'badge-lost-open' }}" style="padding: 1px 7px; font-size: 10px;">
                                {{ ucfirst($thread->item->type) }}
                            </span>
                            <span data-thread-snippet style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $thread->last_message ? \Illuminate\Support\Str::limit($thread->last_message->message_body, 32) : 'Start conversation' }}
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div style="padding: 36px 14px; text-align: center;">
                    <p class="caption" style="margin: 0 0 12px; font-size: 13px;">No chat threads yet.</p>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline btn-sm">Open Posts Feed</a>
                </div>
            @endforelse
            <p class="caption" data-thread-filter-empty hidden style="padding: 20px 10px; text-align: center; margin: 0;">No matching conversations.</p>
        </div>
    </div>

    {{-- Right Column: Active 1-on-1 Chat Thread --}}
    <div class="card-surface" style="grid-column: span 2 / span 2; display: flex; flex-direction: column; min-height: 500px; padding: 18px 20px;"
         @if($activeItem && $activePeer)
             data-chat-thread
             data-poll-url="{{ route('messages.thread') }}"
             data-item-id="{{ $activeItem->id }}"
             data-peer-id="{{ $activePeer->id }}"
             data-thread-key="{{ $activeThread->key }}"
             data-last-message-id="{{ $messages->max('id') ?? 0 }}"
             data-terminal="{{ $activeItem->isTerminal() ? 'true' : 'false' }}"
         @endif>
        @if($activeItem && $activePeer)
            @php
                $activePeerInitials = collect(explode(' ', trim($activePeer->name)))
                    ->filter()
                    ->take(2)
                    ->map(fn ($p) => strtoupper(mb_substr($p, 0, 1)))
                    ->implode('');
            @endphp
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid var(--border-color); margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="ig-avatar" style="width: 42px; height: 42px;">{{ $activePeerInitials ?: 'U' }}</span>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <h2 class="section-title" style="color: var(--text-dark);">{{ $activePeer->name }}</h2>
                            @if($activePeer->isSecurity())
                                <span class="badge badge-claimed">Security</span>
                            @endif
                            <span class="caption">&bull; Re: <strong>{{ $activeItem->title }}</strong></span>
                        </div>
                        <div class="caption" style="margin-top: 2px;">
                            {{ $activeItem->category->name }} &bull; {{ $activeItem->building }}
                            @if($activeItem->custody_location)
                                &bull; Custody: {{ $activeItem->custody_location }}
                            @endif
                        </div>
                    </div>
                </div>
                <a href="{{ route('items.show', ['item' => $activeItem, 'chat_peer' => $activePeer->id]) }}" class="btn btn-outline btn-sm" style="border-radius: 9999px;">View Post &rarr;</a>
            </div>

            <div data-chat-scroll style="flex: 1; max-height: 360px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; background: var(--canvas); margin-bottom: 14px; display: flex; flex-direction: column; gap: 10px;">
                @forelse($messages as $msg)
                    @php $isMine = $msg->sender_id === auth()->id(); @endphp
                    <div class="{{ $isMine ? 'chat-bubble-sent' : 'chat-bubble-recv' }}" data-message-id="{{ $msg->id }}">
                        <div class="chat-meta" style="display: flex; justify-content: space-between; gap: 12px; font-size: 11px; margin-bottom: 3px;">
                            <strong>{{ $isMine ? 'You' : $msg->sender->name }}</strong>
                            <span>{{ $msg->created_at->format('d M, H:i') }}</span>
                        </div>
                        <div style="font-size: 13.5px; line-height: 1.45;">{{ $msg->message_body }}</div>
                    </div>
                @empty
                    <p class="caption" data-chat-empty style="margin: auto; text-align: center;">Start a private conversation with {{ $activePeer->name }} below.</p>
                @endforelse
            </div>

            @if($activeItem->isTerminal())
                <div class="alert alert-info" style="margin-bottom: 0;">
                    <div class="alert-body">This chat thread is read-only because the item is <strong>{{ $activeItem->status }}</strong>.</div>
                </div>
            @else
                <div class="chat-suggestions">
                    <button type="button" class="chat-suggestion-chip" data-chat-suggest="Hi {{ $activePeer->name }}, following up regarding {{ $activeItem->title }}." data-chat-target="#chat-body">Follow up on post</button>
                    <button type="button" class="chat-suggestion-chip" data-chat-suggest="Could we confirm the pickup schedule at {{ $activeItem->custody_location ?? 'UPH Security' }}?" data-chat-target="#chat-body">Ask pickup schedule</button>
                </div>
                <form action="{{ route('items.messages.store', $activeItem) }}" method="POST" data-chat-form>
                    @csrf
                    <input type="hidden" name="redirect_to" value="messages">
                    <input type="hidden" name="receiver_id" value="{{ $activePeer->id }}">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input id="chat-body" type="text" name="message_body" maxlength="1000" placeholder="Message {{ $activePeer->name }}..." required style="flex: 1; border-radius: 9999px; padding-left: 16px;">
                        <button type="submit" class="btn btn-primary" style="border-radius: 9999px; padding: 10px 22px;">Send</button>
                    </div>
                </form>
            @endif
        @else
            <div style="margin: auto; text-align: center; padding: 48px 20px;">
                <div style="width: 56px; height: 56px; border-radius: 9999px; background: var(--primary-light); color: var(--primary-navy); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <h2 class="section-title" style="margin-bottom: 6px;">Your Direct Messages</h2>
                <p class="caption" style="max-width: 380px; margin: 0 auto 18px; font-size: 13px;">
                    Select any post from the Posts feed to start a private 1-on-1 encrypted chat with the reporter or campus security.
                </p>
                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm" style="border-radius: 9999px; padding: 8px 20px;">Explore Posts</a>
            </div>
        @endif
    </div>
</div>
@endsection
