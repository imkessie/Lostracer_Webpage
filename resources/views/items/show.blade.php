@extends('layouts.app')

@section('title', $item->title)

@section('konten')
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="{{ route('dashboard', ['type' => $item->type]) }}">Posts</a>
    <span>/</span>
    <a href="{{ route('dashboard', ['type' => $item->type]) }}">
        {{ $item->type === 'found' ? 'Found Posts' : 'Lost Posts' }}
    </a>
    <span>/</span>
    <span style="color: var(--text-dark); font-weight: 600;">{{ $item->title }}</span>
</nav>

{{-- Found Item Lifecycle Stepper --}}
@if($item->type === 'found')
    @php
        $hasApproved = $item->hasApprovedClaim();
        $step = 1;
        if ($item->status === 'claimed' && !$hasApproved) {
            $step = 2;
        } elseif ($item->status === 'claimed' && $hasApproved) {
            $step = 3;
        } elseif ($item->status === 'returned') {
            $step = 4;
        }
        $steps = [
            1 => 'Deposited at Custody',
            2 => 'Under Review',
            3 => 'Pickup Code Issued',
            4 => 'Handed Over',
        ];
    @endphp
    <div class="card-surface" style="padding: 16px 22px; margin-bottom: 22px;">
        <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;">
            @foreach($steps as $num => $label)
                @php
                    $isDone = $item->status !== 'expired' && $step >= $num;
                    $isCurrent = $item->status !== 'expired' && $step === $num;
                @endphp
                <div class="lifecycle-step {{ $isCurrent ? 'is-current' : '' }}" style="display: flex; flex-direction: column; gap: 8px;">
                    <div style="height: 4px; border-radius: 9999px; background: {{ $isDone ? '#1466EA' : 'var(--border-color)' }};"></div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="step-circle" style="width: 22px; height: 22px; border-radius: 9999px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;
                            background: {{ $isDone ? '#1466EA' : 'var(--canvas)' }};
                            color: {{ $isDone ? '#FFFFFF' : 'var(--text-muted)' }};
                            border: 1px solid {{ $isDone ? '#1466EA' : 'var(--border-color)' }};">
                            @if($isDone && !$isCurrent)
                                &#10003;
                            @else
                                {{ $num }}
                            @endif
                        </span>
                        <span style="font-size: 12px; font-weight: {{ $isCurrent ? '700' : '500' }}; color: {{ $isDone ? 'var(--text-dark)' : 'var(--text-muted)' }};">
                            {{ $label }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@php
    $authorInitials = collect(explode(' ', trim($item->user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($p) => strtoupper(mb_substr($p, 0, 1)))
        ->implode('');
@endphp

<div class="detail-layout">
    {{-- Left Column: Post Card --}}
    <div class="ig-card" style="padding: 0;">
        {{-- Author Header --}}
        <div class="ig-card-header" style="padding: 16px 22px; border-bottom: 1px solid var(--border-color);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="ig-avatar" style="width: 40px; height: 40px; font-size: 13px;">{{ $authorInitials ?: 'U' }}</span>
                <div>
                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 14px;">
                        <span>{{ $item->user->name }}</span>
                        @if($item->user->isSecurity())
                            <span class="badge badge-claimed" style="padding: 1px 8px; font-size: 10.5px;">Security</span>
                        @endif
                    </div>
                    <div class="caption">{{ $item->building }} &bull; {{ $item->created_at->diffForHumans() }}</div>
                </div>
            </div>

            <div style="display: flex; gap: 6px; align-items: center;">
                <span class="badge {{ $item->type === 'found' ? 'badge-found-open' : 'badge-lost-open' }}">
                    {{ $item->type === 'found' ? 'Found' : 'Lost' }}
                </span>
                @if($item->status === 'open')
                    <span class="badge {{ $item->type === 'found' ? 'badge-found-open' : 'badge-lost-open' }}">Open</span>
                @elseif($item->status === 'claimed')
                    <span class="badge badge-claimed">Claimed</span>
                @elseif($item->status === 'returned')
                    <span class="badge badge-returned">Returned</span>
                @else
                    <span class="badge badge-expired">Expired</span>
                @endif
            </div>
        </div>

        {{-- Photo Frame (if uploaded) --}}
        @if($item->photo_path)
            <div style="position: relative; background: var(--surface-muted); border-bottom: 1px solid var(--border-color);">
                <img src="{{ route('items.photo', $item) }}"
                     alt="{{ $item->title }}"
                     @if(!$item->is_sensitive || auth()->user()->isSecurity()) data-lightbox @endif
                     class="{{ ($item->is_sensitive && !auth()->user()->isSecurity()) ? 'sensitive-blur' : '' }}"
                     style="width: 100%; max-height: 440px; object-fit: cover; display: block;">
                @if($item->is_sensitive && !auth()->user()->isSecurity())
                    <div style="position: absolute; bottom: 14px; left: 14px; right: 14px; background: rgba(15, 23, 42, 0.84); color: #FFFFFF; padding: 10px 14px; border-radius: 10px; font-size: 12px;">
                        Photo is blurred to protect sensitive identity data. Only security officers can view the original image.
                    </div>
                @endif
            </div>
        @endif

        <div style="padding: 22px;">
            @if($item->is_sensitive || $item->is_hidden)
                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                    @if($item->is_sensitive)
                        <span class="badge badge-warning">Sensitive Item</span>
                    @endif
                    @if($item->is_hidden)
                        <span class="badge badge-danger">Hidden</span>
                    @endif
                </div>
            @endif

            <h1 class="page-title" style="margin-bottom: 8px;">{{ $item->title }}</h1>
            <p style="margin: 0 0 20px; white-space: pre-line; color: var(--text-muted); font-size: 14px; line-height: 1.6;">{{ $item->description }}</p>

            {{-- Highlighted Custody Card (Found Items) --}}
            @if($item->type === 'found' && $item->custody_location)
                <div style="padding: 15px 18px; border-radius: 12px; background: linear-gradient(125deg, #06246E 0%, #0A43B8 100%); color: #FFFFFF; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 10px 24px -10px rgba(6, 36, 110, 0.48);">
                    <span style="width: 36px; height: 36px; border-radius: 10px; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.22); color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </span>
                    <div style="min-width: 0;">
                        <div class="meta-item-label" style="color: #BFDBFE; margin-bottom: 2px;">Currently Held In Custody</div>
                        <div style="font-size: 14px; font-weight: 700; color: #FFFFFF;">{{ $item->custody_location }}</div>
                        @if($item->deposit_note)
                            <div class="caption" style="color: #DBEAFE; margin-top: 2px;">{{ $item->deposit_note }}</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Clean Key-Value Metadata Grid --}}
            <div class="meta-grid" style="margin-bottom: 18px;">
                <div>
                    <div class="meta-item-label">Category</div>
                    <div class="meta-item-value">{{ $item->displayCategory() }}</div>
                </div>
                <div>
                    <div class="meta-item-label">Event Date</div>
                    <div class="meta-item-value">{{ $item->event_date->format('d M Y') }}</div>
                </div>
                <div>
                    <div class="meta-item-label">Building &amp; Area</div>
                    <div class="meta-item-value">{{ $item->building }} &mdash; {{ $item->floor_detail }}</div>
                </div>
                @if($item->type === 'found')
                    <div>
                        <div class="meta-item-label">Retention Until</div>
                        <div class="meta-item-value">
                            {{ $item->expires_at?->format('d M Y') ?? '-' }}
                        </div>
                        @if(auth()->user()->isSecurity() && $item->isDueForExtendedShelf())
                            <span class="badge badge-warning" style="margin-top: 5px;">Move to Extended Shelf</span>
                        @endif
                    </div>
                @else
                    <div>
                        <div class="meta-item-label">Posted By</div>
                        <div class="meta-item-value">{{ $item->user->name }}</div>
                    </div>
                @endif
            </div>

            {{-- Finder's Hidden Detail (Security Only) --}}
            @if(auth()->user()->isSecurity() && $item->type === 'found')
                <div class="alert alert-info" style="margin-bottom: 18px;">
                    <svg class="alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <div class="alert-body">
                        <strong>Hidden Detail (Security Only):</strong><br>
                        {{ $item->hidden_detail ?: 'No hidden distinguishing notes provided.' }}
                    </div>
                </div>
            @endif

            {{-- Hashtags & Share / Save Bar --}}
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 18px;">
                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    @foreach($item->tags as $tag)
                        <a href="{{ route('dashboard', ['type' => $item->type, 'q' => '#' . $tag->name]) }}" class="tag-pill">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    @if(auth()->user()->hasRole(['user', 'security']))
                        <button type="button"
                                class="btn btn-outline btn-sm bookmark-btn"
                                data-bookmark-btn
                                data-item-id="{{ $item->id }}"
                                data-item-title="{{ $item->title }}"
                                data-item-type="{{ $item->type }}"
                                data-item-url="{{ route('items.show', $item) }}"
                                aria-pressed="false">
                            Save Post
                        </button>
                    @endif
                    <button type="button"
                            class="btn btn-outline btn-sm"
                            data-copy-text="{{ route('items.show', $item) }}"
                            data-copy-toast="Post link copied to clipboard.">
                        Share Link
                    </button>
                </div>
            </div>

            {{-- Post Action Buttons --}}
            @if($canEditItem || $canTakeDownItem || $canCloseLostReport || (auth()->user()->isSecurity() && $item->type === 'found' && !$item->isTerminal()))
                <div style="display: flex; flex-wrap: wrap; gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                    @if($canEditItem)
                        <a href="{{ route('items.edit', $item) }}" class="btn btn-outline btn-sm">Edit Post</a>
                    @endif

                    @if($canCloseLostReport)
                        <form action="{{ route('items.close', $item) }}"
                              method="POST"
                              data-confirm="Mark this lost post as found/returned? This status is final."
                              data-confirm-title="Close Lost Post"
                              data-confirm-label="Yes, Close Post">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Item Recovered (Close Post)</button>
                        </form>
                    @endif

                    @if($canTakeDownItem)
                        <form action="{{ route('items.destroy', $item) }}"
                              method="POST"
                              data-confirm="Cancel and take down this post? The listing will be permanently removed."
                              data-confirm-title="Cancel & Take Down Post"
                              data-confirm-variant="danger"
                              data-confirm-label="Yes, Take Down Post">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Cancel &amp; Take Down Post</button>
                        </form>
                    @endif

                    @if(auth()->user()->isSecurity() && $item->type === 'found' && !$item->isTerminal())
                        <a href="{{ route('items.location', $item) }}" class="btn btn-primary btn-sm">
                            Relocate Custody / Edit Flags
                        </a>
                    @endif
                </div>
            @endif

            {{-- Security Moderation Panel --}}
            @if(auth()->user()->isSecurity())
                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 22px 0;">
                <h3 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--danger);">Security Moderation</h3>

                @if($item->status === 'expired' && !$item->is_hidden)
                    <form action="{{ route('security.items.dispose', $item) }}" method="POST" style="margin-bottom: 14px; padding: 14px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--canvas);">
                        @csrf
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="dispose_notes">Record Item Disposal / Donation</label>
                            <input id="dispose_notes" type="text" name="notes" placeholder="e.g. Donated per campus policy." required>
                        </div>
                        <button type="submit" class="btn btn-outline btn-sm">Record Item Disposed</button>
                    </form>
                @endif

                <form action="{{ route('security.items.delete', $item) }}"
                      method="POST"
                      class="danger-box"
                      data-confirm="Deleted posts cannot be recovered, and this action is recorded permanently in the audit log."
                      data-confirm-title="Delete Violating Post"
                      data-confirm-variant="danger"
                      data-confirm-label="Delete Permanently">
                    @csrf
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label for="delete_reason" class="danger-box-label">Delete Violating Post</label>
                        <input id="delete_reason" type="text" name="reason" placeholder="Written reason (e.g. duplicate, spam, personal contact exposed)" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">Delete Post &amp; Log Audit</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Right Column: In-App Chat & Claim Ownership --}}
    <div style="display: flex; flex-direction: column; gap: 20px;">
        @if(!auth()->user()->isItAdmin())
            <div class="card-surface" id="chat-column" style="padding: 20px;"
                 @if($activeChatPeer)
                     data-chat-thread
                     data-poll-url="{{ route('messages.thread') }}"
                     data-item-id="{{ $item->id }}"
                     data-peer-id="{{ $activeChatPeer->id }}"
                     data-last-message-id="{{ $messages->max('id') ?? 0 }}"
                     data-terminal="{{ $item->isTerminal() ? 'true' : 'false' }}"
                 @endif>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <div style="display: inline-flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-navy)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <h2 class="section-title" style="font-size: 17px;">In-App Chat</h2>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="badge badge-claimed">Encrypted</span>
                        <a href="{{ route('messages.index', ['item' => $item->id, 'peer' => $activeChatPeer?->id]) }}" class="btn btn-outline btn-sm">Full Inbox</a>
                    </div>
                </div>
                <p class="caption" style="margin: 0 0 12px;">
                    Direct messages with the reporter or security.
                </p>

                {{-- Switcher for separated 1-on-1 threads when multiple people chat on this post --}}
                @if($chatRecipients->count() > 1)
                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                        @foreach($chatRecipients as $peerOption)
                            @php $isSelectedPeer = $activeChatPeer && $activeChatPeer->id === $peerOption->id; @endphp
                            <a href="{{ route('items.show', ['item' => $item, 'chat_peer' => $peerOption->id]) }}#chat-column"
                               class="tag-pill {{ $isSelectedPeer ? 'is-active' : '' }}"
                               style="padding: 4px 11px;">
                                {{ $peerOption->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                <div data-chat-scroll style="max-height: 270px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 12px; padding: 14px; background: var(--canvas); margin-bottom: 14px; display: flex; flex-direction: column; gap: 10px;">
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
                        <p class="caption" data-chat-empty style="margin: 18px 0; text-align: center;">
                            {{ $activeChatPeer ? 'No messages with ' . $activeChatPeer->name . ' yet.' : 'No messages yet.' }}
                        </p>
                    @endforelse
                </div>

                @if($item->isTerminal())
                    <div class="alert alert-info" style="margin-bottom: 0;">
                        <div class="alert-body">This chat thread is read-only because the item status is <strong>{{ $item->status }}</strong>.</div>
                    </div>
                @elseif($activeChatPeer)
                    <div class="chat-suggestions">
                        @if($item->type === 'found')
                            <button type="button" class="chat-suggestion-chip" data-chat-suggest="Hi {{ $activeChatPeer->name }}, is this item still at {{ $item->custody_location ?? 'campus custody' }}?" data-chat-target="#message_body">Is it still in custody?</button>
                            <button type="button" class="chat-suggestion-chip" data-chat-suggest="Hi, I think this belongs to me. Can I confirm a few details?" data-chat-target="#message_body">Confirm item details</button>
                        @else
                            <button type="button" class="chat-suggestion-chip" data-chat-suggest="Hi {{ $activeChatPeer->name }}, I might have seen or found this item near {{ $item->building }}." data-chat-target="#message_body">I may have found this</button>
                        @endif
                    </div>
                    <form action="{{ route('items.messages.store', $item) }}" method="POST" data-chat-form>
                        @csrf
                        <input type="hidden" name="receiver_id" value="{{ $activeChatPeer->id }}">
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="message_body">Message to {{ $activeChatPeer->name }}</label>
                            <textarea id="message_body" name="message_body" rows="2" maxlength="1000" data-chat-input data-autogrow placeholder="Type your message to {{ $activeChatPeer->name }} (Enter to send)..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Send Message</button>
                    </form>
                @else
                    <p class="caption" style="margin: 0;">No other recipients are currently available on this post.</p>
                @endif
            </div>
        @endif

        @if($item->type === 'found' && auth()->user()->isUser())
            <div class="card-surface" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <h2 class="section-title" style="font-size: 17px;">Claim Ownership</h2>
                    @if($userClaims->isNotEmpty())
                        <span class="badge badge-claimed">{{ $userClaims->count() }} {{ $userClaims->count() === 1 ? 'Attempt' : 'Attempts' }}</span>
                    @endif
                </div>

                @if($userClaims->isNotEmpty())
                    <div style="margin-bottom: 14px; display: flex; flex-direction: column; gap: 8px;">
                        @foreach($userClaims as $myClaim)
                            <div style="padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--canvas);">
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12.5px;">
                                    <strong>Attempt #{{ $myClaim->attempt_no }}</strong>
                                    @if($myClaim->status === 'approved')
                                        <span class="badge badge-found-open">APPROVED</span>
                                    @elseif($myClaim->status === 'rejected')
                                        <span class="badge badge-danger">REJECTED</span>
                                    @elseif($myClaim->status === 'pending')
                                        <span class="badge badge-claimed">PENDING</span>
                                    @else
                                        <span class="badge badge-expired">{{ strtoupper($myClaim->status) }}</span>
                                    @endif
                                </div>
                                @if($myClaim->status === 'approved' && $myClaim->pickup_code)
                                    <div class="pickup-code-box">
                                        <div class="pickup-code-label">One-Time Pickup Code:</div>
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                            <div style="font-size: 20px; font-weight: 800; letter-spacing: 1px;">{{ $myClaim->pickup_code }}</div>
                                            <button type="button" class="btn btn-outline btn-sm" data-copy-text="{{ $myClaim->pickup_code }}">Copy</button>
                                        </div>
                                        <div class="pickup-code-note">
                                            Bring your UPH ID card to {{ $item->custody_location }} within 7 days.
                                        </div>
                                    </div>
                                @elseif($myClaim->status === 'rejected' && $myClaim->verification_notes)
                                    <p style="margin: 6px 0 0; color: var(--danger); font-size: 12.5px;">
                                        <strong>Reason:</strong> {{ $myClaim->verification_notes }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($canSubmitClaim)
                    <p class="caption" style="margin: 0 0 14px; font-size: 13px;">
                        Submit ownership proof (photo with the item, receipt, serial number, or unlisted interior details). Proof files are visible only to Security.
                    </p>
                    <form action="{{ route('items.claim', $item) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label for="proof_description">Proof Description</label>
                            <textarea id="proof_description" name="proof_description" rows="3" maxlength="1000" data-char-counter placeholder="Describe hidden details, serial number, or proof of ownership..." required>{{ old('proof_description') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="proof_file">Upload Proof File (JPG, PNG, or PDF &bull; Max 4MB)</label>
                            <div class="file-dropzone" data-file-dropzone>
                                <input id="proof_file" type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" data-max-kb="4096" required>
                                <div class="file-meta-row" data-file-meta>
                                    <span data-file-name></span>
                                    <button type="button" class="btn btn-outline btn-sm" data-file-clear>Remove</button>
                                </div>
                                <div data-file-preview style="display: none; margin-top: 10px;">
                                    <img data-file-img src="#" alt="Proof preview" style="max-height: 160px; border-radius: 8px; border: 1px solid var(--border-color);">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Claim</button>
                    </form>
                @elseif($item->user_id === auth()->id())
                    <div class="alert alert-info" style="margin-bottom: 0;">
                        <div class="alert-body">You submitted this found post and cannot claim your own item.</div>
                    </div>
                @elseif($hasActiveUserClaim)
                    <div class="alert alert-info" style="margin-bottom: 0;">
                        <div class="alert-body">
                            Your claim is active. Track updates in <a href="{{ route('claims.index') }}" style="font-weight: 700;">My Claims</a>.
                        </div>
                    </div>
                @elseif($rejectedAttempts >= $maxAttempts)
                    <div class="alert alert-danger" style="margin-bottom: 0;">
                        <div class="alert-body">
                            Maximum claim attempts ({{ $maxAttempts }}x) reached. Please visit the security post for in-person verification.
                        </div>
                    </div>
                @else
                    <p class="caption" style="margin: 0; font-size: 13px;">
                        New claims are currently paused because the post status is <strong>{{ $item->status }}</strong> or an approved claim is awaiting pickup.
                    </p>
                @endif
            </div>
        @endif

        @if(auth()->user()->isSecurity() && $item->type === 'found')
            <div class="card-surface" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px;">
                    <h2 class="section-title" style="font-size: 17px;">Submitted Claims ({{ $item->claims->count() }})</h2>
                    <a href="{{ route('claims.index') }}" class="btn btn-primary btn-sm">Process Handover / Queue</a>
                </div>

                @if($item->claims->isEmpty())
                    <p class="caption" style="margin: 0; font-size: 13px;">No claims have been submitted for this post yet.</p>
                @else
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        @foreach($item->claims as $claim)
                            <div style="padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--canvas);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong>{{ $claim->user->name }}</strong>
                                    <span class="badge badge-claimed">{{ strtoupper($claim->status) }}</span>
                                </div>
                                <p style="margin: 6px 0 10px; font-size: 13px; color: var(--text-dark);">{{ $claim->proof_description }}</p>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <a href="{{ route('claims.proof', $claim) }}" target="_blank" class="btn btn-outline btn-sm">
                                        View Private Proof File
                                    </a>
                                    @if($claim->handover_at)
                                        <a href="{{ route('claims.bast', $claim) }}" class="btn btn-success btn-sm">
                                            Print BAST Slip
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if(auth()->user()->isSecurity())
            <div class="card-surface" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <h2 class="section-title" style="font-size: 17px;">Item Audit Trail</h2>
                    <a href="{{ route('audit.index') }}" class="btn btn-outline btn-sm">All Audit Logs</a>
                </div>

                @if($item->auditLogs->isEmpty())
                    <p class="caption" style="margin: 0; font-size: 13px;">No audit actions recorded for this post yet.</p>
                @else
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        @foreach($item->auditLogs->sortByDesc('id') as $log)
                            <div style="padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--canvas); font-size: 12.5px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span class="badge badge-claimed">{{ $log->action }}</span>
                                    <span class="caption">{{ $log->created_at->format('d M Y, H:i') }}</span>
                                </div>
                                <div style="color: var(--text-dark);">{{ $log->notes }}</div>
                                <div class="caption" style="margin-top: 3px;">By: {{ $log->user?->name ?? 'System' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
