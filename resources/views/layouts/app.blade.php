<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') &mdash; Lostracer UPH</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/lostracer-icon.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/lostracer-logo.png') }}">

    <!-- Synchronous theme init to prevent light flash on reload -->
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('lostracer-theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.dataset.theme = saved || (prefersDark ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>

    <!-- Plus Jakarta Sans & Inter Fonts (Blueprint Section 10) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/lostracer.css') }}">
</head>
<body class="@guest is-guest @endguest">
    <div id="lt-progress" class="lt-progress-bar no-print" aria-hidden="true"></div>

    {{-- Top navigation is rendered only for authenticated users --}}
    @auth
    @php
        $userInitials = collect(explode(' ', trim(auth()->user()->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $unreadMessagesCount = auth()->user()->isItAdmin()
            ? 0
            : \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count();
    @endphp
    <nav class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('dashboard') }}" class="brand" aria-label="Lostracer">
                @include('partials.brand-wordmark', ['class' => 'brand-wordmark'])
            </a>

            <button type="button" class="nav-toggle" id="nav-toggle-btn" aria-label="Toggle navigation menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>

            <div class="nav-menu" id="nav-menu">
                <div class="nav-links">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard', 'items.show', 'items.edit', 'items.location') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Posts
                </a>

                @if(auth()->user()->hasRole(['user', 'security']))
                    <a href="{{ route('messages.index') }}" data-chat-nav-link class="nav-link {{ request()->routeIs('messages.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Chat
                        @if($unreadMessagesCount > 0)
                            <span class="nav-badge">{{ $unreadMessagesCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('claims.index') }}" class="nav-link {{ request()->routeIs('claims.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m16 16 2 2 4-4"/><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/>
                        </svg>
                        {{ auth()->user()->isSecurity() ? 'Verification' : 'My Claims' }}
                    </a>
                @endif

                @if(auth()->user()->isSecurity())
                    <a href="{{ route('security.users') }}" class="nav-link {{ request()->routeIs('security.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        </svg>
                        Users
                    </a>
                    <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                        </svg>
                        Audit Logs
                    </a>
                @endif

                @if(auth()->user()->isItAdmin())
                    <a href="{{ route('admin.system') }}" class="nav-link {{ request()->routeIs('admin.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                        System &amp; Domain
                    </a>
                @endif

                </div>

                <div class="nav-actions">
                <button type="button" class="cmdk-trigger" data-open-cmdk title="Quick Command Palette (Cmd/Ctrl+K)" aria-label="Open command palette">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <kbd>&#8984;K</kbd>
                </button>
                @if(auth()->user()->hasRole(['user', 'security']))
                    <button type="button" class="theme-toggle-btn watchlist-nav-btn" data-open-watchlist title="Saved Posts Watchlist" aria-label="Open saved posts watchlist">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
                        </svg>
                        <span class="watchlist-dot" data-watchlist-count hidden>0</span>
                    </button>
                @endif
                <button type="button" class="theme-toggle-btn" data-theme-toggle aria-label="Toggle dark mode" title="Toggle theme">
                    <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                </button>
                @if(auth()->user()->hasRole(['user', 'security']))
                    <a href="{{ route('items.create') }}" class="nav-link nav-cta">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Post
                    </a>
                @endif

                <div class="nav-user-chip">
                    <span class="user-avatar">{{ $userInitials ?: 'U' }}</span>
                    <span class="nav-user-name" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</span>
                    <form action="{{ route('logout') }}" method="POST" style="display: inline-flex;">
                        @csrf
                        <button type="submit" class="nav-logout-btn" title="Sign out" aria-label="Sign out">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                        </button>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </nav>
    @endauth

    <main class="container">
        @if(session('success'))
            <div class="alert alert-success no-print" role="status" data-auto-dismiss="5500">
                <svg class="alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <div class="alert-body">{{ session('success') }}</div>
                <button type="button" class="alert-close" data-alert-close aria-label="Close">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger no-print" role="alert">
                <svg class="alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div class="alert-body">{{ session('error') }}</div>
                <button type="button" class="alert-close" data-alert-close aria-label="Close">&times;</button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger no-print" role="alert">
                <svg class="alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div class="alert-body">
                    <strong>Please check the following errors:</strong>
                    <ul style="margin: 6px 0 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="alert-close" data-alert-close aria-label="Close">&times;</button>
            </div>
        @endif

        @yield('konten')
    </main>

    @auth
    <footer class="site-footer no-print">
        <div class="footer-inner">
            <div class="footer-brand">
                <img src="{{ asset('images/lostracer-logo.png') }}" alt="">
                <span>&copy; Lostracer</span>
            </div>
            <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap;">
                <a href="{{ route('dashboard') }}" class="footer-link">Posts</a>
                @if(auth()->user()->hasRole(['user', 'security']))
                    <a href="{{ route('messages.index') }}" class="footer-link">Chat</a>
                    <a href="{{ route('claims.index') }}" class="footer-link">{{ auth()->user()->isSecurity() ? 'Verification' : 'My Claims' }}</a>
                @endif
            </div>
        </div>
    </footer>
    @endauth

    {{-- Global Confirmation Modal --}}
    <div id="confirm-modal" class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <div class="modal-panel">
            <h3 id="confirm-modal-title" style="margin: 0 0 8px; font-size: 18px; color: var(--text-dark);">Confirm Action</h3>
            <p id="confirm-modal-message" style="margin: 0 0 20px; color: var(--text-muted); font-size: 14px;"></p>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" id="confirm-modal-cancel" class="btn btn-outline">Cancel</button>
                <button type="button" id="confirm-modal-submit" class="btn btn-primary">Continue</button>
            </div>
        </div>
    </div>

    {{-- Global Lightbox Modal --}}
    <div id="lightbox-modal" class="lightbox-backdrop no-print" role="dialog" aria-modal="true" aria-label="Image Preview">
        <button type="button" id="lightbox-close" class="lightbox-close" aria-label="Close preview">&times;</button>
        <div class="lightbox-panel">
            <img id="lightbox-image" src="" alt="">
            <div id="lightbox-caption" style="color: #E2E8F0; font-size: 13px; text-align: center;"></div>
        </div>
    </div>

    @auth
    {{-- Slide-Over Quick Preview Drawer --}}
    <div id="quick-view-drawer" class="drawer-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="qv-title">
        <div class="drawer-panel">
            <div class="drawer-header">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span id="qv-type-badge" class="badge badge-found-open">Found</span>
                    <span id="qv-status-badge" class="badge badge-claimed">Open</span>
                    <span id="qv-sensitive-badge" class="badge badge-warning" hidden>Sensitive</span>
                </div>
                <button type="button" class="drawer-close-btn" data-drawer-close="quick-view-drawer" aria-label="Close preview">&times;</button>
            </div>
            <div class="drawer-body">
                <div id="qv-media-wrap" class="drawer-media-wrap">
                    <img id="qv-img" src="" alt="">
                    <div id="qv-blur-note" class="drawer-blur-note" hidden>
                        Photo is blurred to protect sensitive ID data.
                    </div>
                </div>
                <div id="qv-category" class="post-category" style="margin-top: 14px;"></div>
                <h2 id="qv-title" class="page-title" style="font-size: 21px; margin: 4px 0 8px;"></h2>
                <p id="qv-desc" style="margin: 0 0 16px; color: var(--text-body); font-size: 13.5px; line-height: 1.6; white-space: pre-line;"></p>

                <div id="qv-custody-box" class="callout-info" style="padding: 12px 14px; border-radius: 10px; margin-bottom: 16px;" hidden>
                    <div class="meta-item-label" style="margin-bottom: 2px;">Currently Held In Custody</div>
                    <div id="qv-custody-text" style="font-weight: 700; font-size: 13.5px;"></div>
                </div>

                <div class="meta-grid" style="margin-bottom: 16px;">
                    <div>
                        <div class="meta-item-label">Building &amp; Area</div>
                        <div id="qv-location" class="meta-item-value"></div>
                    </div>
                    <div>
                        <div class="meta-item-label">Event Date</div>
                        <div id="qv-date" class="meta-item-value"></div>
                    </div>
                    <div>
                        <div class="meta-item-label">Posted By</div>
                        <div id="qv-author" class="meta-item-value"></div>
                    </div>
                    <div>
                        <div class="meta-item-label">Posted</div>
                        <div id="qv-posted" class="meta-item-value"></div>
                    </div>
                </div>

                <div id="qv-tags" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
            </div>
            <div class="drawer-footer">
                <button type="button" id="qv-bookmark-btn" class="btn btn-outline" data-bookmark-btn>Save</button>
                <a id="qv-chat-link" href="#" class="btn btn-outline">Direct Chat</a>
                <a id="qv-open-link" href="#" class="btn btn-primary" style="flex: 1;">Open Full Post</a>
            </div>
        </div>
    </div>

    @if(auth()->user()->hasRole(['user', 'security']))
    {{-- Saved Watchlist Slide-Over Drawer --}}
    <div id="watchlist-drawer" class="drawer-backdrop no-print" role="dialog" aria-modal="true" aria-labelledby="wl-title">
        <div class="drawer-panel">
            <div class="drawer-header">
                <div>
                    <h2 id="wl-title" class="section-title">Saved Posts Watchlist</h2>
                    <p class="caption" style="margin: 2px 0 0;">Quick access to posts you bookmarked on this device.</p>
                </div>
                <button type="button" class="drawer-close-btn" data-drawer-close="watchlist-drawer" aria-label="Close watchlist">&times;</button>
            </div>
            <div id="watchlist-items" class="drawer-body"></div>
            <div class="drawer-footer">
                <button type="button" id="watchlist-clear-btn" class="btn btn-outline btn-sm">Clear All</button>
                <button type="button" class="btn btn-primary btn-sm" data-drawer-close="watchlist-drawer" style="margin-left: auto;">Done</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Command Palette Modal (Cmd/Ctrl + K) --}}
    <div id="command-palette-modal" class="modal-backdrop no-print" role="dialog" aria-modal="true" aria-label="Quick Command Palette">
        <div class="cmdk-panel">
            <div class="cmdk-search-row">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-muted); flex-shrink: 0;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input id="cmdk-input" type="text" placeholder="Type a command or destination..." autocomplete="off">
                <kbd class="cmdk-esc">ESC</kbd>
            </div>
            <div id="cmdk-list" class="cmdk-list" role="listbox">
                <a href="{{ route('dashboard', ['scope' => 'feed', 'type' => 'found']) }}" class="cmdk-item" data-cmdk-item>
                    <span>Browse Found Posts</span><span class="cmdk-hint">Feed</span>
                </a>
                <a href="{{ route('dashboard', ['scope' => 'feed', 'type' => 'lost']) }}" class="cmdk-item" data-cmdk-item>
                    <span>Browse Lost Posts</span><span class="cmdk-hint">Feed</span>
                </a>
                @if(auth()->user()->hasRole(['user', 'security']))
                    <a href="{{ route('dashboard', ['scope' => 'mine']) }}" class="cmdk-item" data-cmdk-item>
                        <span>My Posts</span><span class="cmdk-hint">Personal</span>
                    </a>
                    <a href="{{ route('items.create') }}" class="cmdk-item" data-cmdk-item>
                        <span>Create New Post</span><span class="cmdk-hint">Action</span>
                    </a>
                    <a href="{{ route('messages.index') }}" class="cmdk-item" data-cmdk-item>
                        <span>Open Direct Chat Inbox</span><span class="cmdk-hint">Chat</span>
                    </a>
                    <a href="{{ route('claims.index') }}" class="cmdk-item" data-cmdk-item>
                        <span>{{ auth()->user()->isSecurity() ? 'Verification & Handover Queue' : 'My Claims & Pickup Codes' }}</span><span class="cmdk-hint">Claims</span>
                    </a>
                    <button type="button" class="cmdk-item" data-cmdk-item data-cmdk-action="open-watchlist">
                        <span>Open Saved Watchlist</span><span class="cmdk-hint">Local</span>
                    </button>
                @endif
                @if(auth()->user()->isSecurity())
                    <a href="{{ route('dashboard', ['scope' => 'archive']) }}" class="cmdk-item" data-cmdk-item>
                        <span>Returned &amp; Expired Archive</span><span class="cmdk-hint">Security</span>
                    </a>
                    <a href="{{ route('security.users') }}" class="cmdk-item" data-cmdk-item>
                        <span>User Account Moderation</span><span class="cmdk-hint">Security</span>
                    </a>
                    <a href="{{ route('audit.index') }}" class="cmdk-item" data-cmdk-item>
                        <span>Audit Logs</span><span class="cmdk-hint">Security</span>
                    </a>
                @endif
                @if(auth()->user()->isItAdmin())
                    <a href="{{ route('admin.system') }}" class="cmdk-item" data-cmdk-item>
                        <span>System &amp; Domain Configuration</span><span class="cmdk-hint">Admin</span>
                    </a>
                @endif
                <button type="button" class="cmdk-item" data-cmdk-item data-cmdk-action="toggle-theme">
                    <span>Toggle Dark / Light Mode</span><span class="cmdk-hint">Theme</span>
                </button>
            </div>
            <div id="cmdk-empty" class="caption" style="padding: 20px; text-align: center; display: none;">No matching commands.</div>
        </div>
    </div>
    @endauth

    {{-- Floating Back to Top --}}
    @auth
    <button type="button" id="back-to-top-btn" class="back-to-top no-print" aria-label="Back to top" title="Back to top">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"/>
        </svg>
    </button>
    @endauth

    <script src="{{ asset('js/lostracer.js') }}?v={{ filemtime(public_path('js/lostracer.js')) }}" defer></script>
</body>
</html>
