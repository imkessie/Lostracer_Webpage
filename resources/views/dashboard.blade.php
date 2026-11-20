@extends('layouts.app')

@section('title', 'Posts')

@section('konten')
    {{-- Hero & Feed Switcher --}}
    <section class="hero-command">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 20px;">
            <div style="min-width: 0; flex: 1 1 460px;">
                <div class="hero-kicker">UPH Lost &amp; Found</div>
                <h1 class="hero-title">Posts</h1>
                <p class="hero-subtitle">Don't replace it, trace it.</p>

                <nav id="hero-tabs" class="hero-stats" aria-label="Post feed views">
                    <a href="{{ route('dashboard', ['scope' => 'feed', 'type' => 'found', 'q' => $q, 'category' => $categoryId, 'building' => $building]) }}"
                       class="hero-stat {{ ($scope === 'feed' && $type === 'found') ? 'is-active' : '' }}"
                       style="--stat-accent: #34D399;"
                       @if($scope === 'feed' && $type === 'found') aria-current="page" @endif>
                        <div class="hero-stat-value">{{ $totalFound }}</div>
                        <div class="hero-stat-label">
                            <span style="width: 6px; height: 6px; border-radius: 9999px; background: #34D399;"></span>
                            Found
                        </div>
                    </a>

                    <a href="{{ route('dashboard', ['scope' => 'feed', 'type' => 'lost', 'q' => $q, 'category' => $categoryId, 'building' => $building]) }}"
                       class="hero-stat {{ ($scope === 'feed' && $type === 'lost') ? 'is-active' : '' }}"
                       style="--stat-accent: #FB7185;"
                       @if($scope === 'feed' && $type === 'lost') aria-current="page" @endif>
                        <div class="hero-stat-value">{{ $totalLost }}</div>
                        <div class="hero-stat-label">
                            <span style="width: 6px; height: 6px; border-radius: 9999px; background: #FB7185;"></span>
                            Lost
                        </div>
                    </a>

                    @if(auth()->user()->hasRole(['user', 'security']))
                        <a href="{{ route('dashboard', ['scope' => 'mine', 'q' => $q, 'category' => $categoryId, 'building' => $building]) }}"
                           class="hero-stat {{ $scope === 'mine' ? 'is-active' : '' }}"
                           style="--stat-accent: #93C5FD;"
                           @if($scope === 'mine') aria-current="page" @endif>
                            <div class="hero-stat-value">{{ $totalMine }}</div>
                            <div class="hero-stat-label">
                                <span style="width: 6px; height: 6px; border-radius: 9999px; background: #93C5FD;"></span>
                                My Posts
                            </div>
                        </a>
                    @endif

                    @if(auth()->user()->isSecurity())
                        <a href="{{ route('dashboard', ['scope' => 'archive', 'q' => $q, 'category' => $categoryId, 'building' => $building]) }}"
                           class="hero-stat {{ $scope === 'archive' ? 'is-active' : '' }}"
                           style="--stat-accent: #E2E8F0;"
                           @if($scope === 'archive') aria-current="page" @endif>
                            <div class="hero-stat-value">{{ $totalArchive }}</div>
                            <div class="hero-stat-label">
                                <span style="width: 6px; height: 6px; border-radius: 9999px; background: #CBD5E1;"></span>
                                Archive
                            </div>
                        </a>
                    @endif
                </nav>
            </div>

            @if(auth()->user()->hasRole(['user', 'security']))
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="{{ route('messages.index') }}" class="btn btn-glass">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Direct Chat
                    </a>
                    <a href="{{ route('items.create') }}" class="btn btn-white">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Post
                    </a>
                </div>
            @endif
        </div>
    </section>

    {{-- Security Extended Shelf Reminder (CUS-08) --}}
    @if(auth()->user()->isSecurity() && $extendedShelfDueCount > 0)
        <div class="alert alert-warning">
            <svg class="alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <div class="alert-body">
                <strong>Shelf Transfer Reminder:</strong>
                <strong>{{ $extendedShelfDueCount }}</strong> found item(s) have passed 3 months in custody and should be moved to the <em>Extended Shelf</em>.
            </div>
        </div>
    @endif

    {{-- Search & Filters --}}
    <div class="feed-toolbar">
        <form id="dashboard-filter-form" action="{{ route('dashboard') }}" method="GET" class="filter-grid" data-live-filter-form>
            <input type="hidden" name="scope" value="{{ $scope }}">
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="input-with-icon" data-search-clearable>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="q" value="{{ $q }}" data-shortcut-search data-live-search-input placeholder="Search items, #hashtags, or buildings (press /)" style="padding-right: 36px;">
                <button type="button" class="input-clear-btn" data-clear-input aria-label="Clear search" hidden>&times;</button>
            </div>

            <select name="category" aria-label="Filter by category" data-auto-submit>
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>

            <select name="building" aria-label="Filter by building" data-auto-submit>
                <option value="">All Buildings</option>
                @foreach($buildings as $bld)
                    <option value="{{ $bld }}" {{ $building === $bld ? 'selected' : '' }}>
                        {{ $bld }}
                    </option>
                @endforeach
            </select>

            <select name="sort" aria-label="Sort posts" data-auto-submit>
                <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Newest First</option>
                <option value="event_desc" {{ $sort === 'event_desc' ? 'selected' : '' }}>Recent Event</option>
                <option value="most_active" {{ $sort === 'most_active' ? 'selected' : '' }}>Most Discussed</option>
            </select>

            <div style="display: flex; gap: 8px;" id="filter-actions">
                <button type="submit" class="btn btn-primary" style="flex: 1; min-height: 42px; padding: 8px 20px;">Search</button>
                <span id="filter-reset-slot">
                    @if($q !== '' || $categoryId || $building !== '' || $sort !== 'latest')
                        <a href="{{ route('dashboard', ['scope' => $scope, 'type' => $type]) }}" class="btn btn-outline" style="min-height: 42px;">Reset</a>
                    @endif
                </span>
            </div>
        </form>

        <div id="toolbar-meta" class="toolbar-meta">
            @if($popularTags->isNotEmpty())
                <div class="trending-row">
                    <span class="caption" style="font-weight: 600; margin-right: 4px; display: inline-flex; align-items: center; gap: 5px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                        </svg>
                        Trending
                    </span>
                    @foreach($popularTags as $popTag)
                        <a href="{{ route('dashboard', ['scope' => 'feed', 'type' => $type, 'q' => '#' . $popTag->name]) }}"
                           class="tag-pill {{ strtolower(ltrim($q, '#')) === $popTag->name ? 'is-active' : '' }}">
                            #{{ $popTag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div style="display: flex; align-items: center; gap: 10px; margin-left: auto; flex-wrap: wrap;">
                <span class="caption">
                    Showing <strong style="color: var(--text-dark);">{{ $items->count() }}</strong> of <strong style="color: var(--text-dark);">{{ $items->total() }}</strong> post(s)
                </span>

                <div class="view-toggle-group" role="group" aria-label="Layout view">
                    <button type="button" class="view-toggle-btn is-active" data-view-mode="grid" title="Grid view" aria-label="Grid view" aria-pressed="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                    </button>
                    <button type="button" class="view-toggle-btn" data-view-mode="list" title="Compact list view" aria-label="List view" aria-pressed="false">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="feed-region" class="feed-region" data-live-feed>

    {{-- Feed --}}
    @if($items->isEmpty())
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </div>
            <h2 class="section-title" style="margin-bottom: 6px;">
                @if($scope === 'mine')
                    No matching personal posts yet
                @elseif($scope === 'archive')
                    No archived posts yet
                @else
                    No matching {{ $type }} posts yet
                @endif
            </h2>
            <p style="margin: 0 0 18px; color: var(--text-muted);">
                Try clearing filters or switch between Found and Lost tabs above.
            </p>
            @if($q !== '' || $categoryId || $building !== '')
                <a href="{{ route('dashboard', ['scope' => $scope, 'type' => $type]) }}" class="btn btn-outline">Show All Posts</a>
            @endif
        </div>
    @else
        <div class="feed-grid">
            @foreach($items as $item)
                @php
                    $authorInitials = collect(explode(' ', trim($item->user->name)))
                        ->filter()
                        ->take(2)
                        ->map(fn ($p) => strtoupper(mb_substr($p, 0, 1)))
                        ->implode('');
                    $isFound = $item->type === 'found';
                    $isBlurred = $item->is_sensitive && !auth()->user()->isSecurity();
                    $quickViewData = [
                        'id' => $item->id,
                        'title' => $item->title,
                        'description' => $item->description,
                        'type' => $item->type,
                        'status' => $item->status,
                        'category' => $item->category->name,
                        'building' => $item->building,
                        'floor_detail' => $item->floor_detail,
                        'event_date' => $item->event_date->format('d M Y'),
                        'custody_location' => $isFound ? $item->custody_location : null,
                        'author' => $item->user->name,
                        'created_human' => $item->created_at->diffForHumans(),
                        'photo_url' => $item->photo_path ? route('items.photo', $item) : null,
                        'is_sensitive' => (bool) $item->is_sensitive,
                        'is_blurred' => $isBlurred,
                        'show_url' => route('items.show', $item),
                        'chat_url' => auth()->user()->hasRole(['user', 'security']) ? route('messages.index', ['item' => $item->id]) : null,
                        'tags' => $item->tags->pluck('name')->values()->all(),
                    ];
                @endphp
                <article class="post-card" data-qv='@json($quickViewData)'>
                    {{-- Media --}}
                    <div class="post-media-frame">
                        <a href="{{ route('items.show', $item) }}" class="post-media" aria-label="{{ $item->title }}">
                            @if($item->photo_path)
                                <img src="{{ route('items.photo', $item) }}"
                                     alt="{{ $item->title }}"
                                     loading="lazy"
                                     class="{{ $isBlurred ? 'sensitive-blur' : '' }}">
                            @else
                                <div class="post-media-placeholder {{ $isFound ? 'is-found' : 'is-lost' }}">
                                    <span class="placeholder-icon" style="color: {{ $isFound ? 'var(--primary-navy)' : 'var(--rose-lost)' }};">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                                        </svg>
                                    </span>
                                    No photo
                                </div>
                            @endif

                            <div class="media-overlay-top">
                                <span class="glass-pill">
                                    <span class="dot" style="background: {{ $isFound ? 'var(--emerald-found)' : 'var(--rose-lost)' }};"></span>
                                    {{ $isFound ? 'Found' : 'Lost' }}
                                </span>

                                <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end;">
                                    @if($item->is_hidden)
                                        <span class="glass-pill is-dark">Hidden</span>
                                    @elseif($item->is_sensitive)
                                        <span class="glass-pill is-dark">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                            </svg>
                                            Sensitive
                                        </span>
                                    @endif

                                    @if($item->status === 'open')
                                        <span class="badge {{ $isFound ? 'badge-found-open' : 'badge-lost-open' }}">Open</span>
                                    @elseif($item->status === 'claimed')
                                        <span class="badge badge-claimed">Claimed</span>
                                    @elseif($item->status === 'returned')
                                        <span class="badge badge-returned">Returned</span>
                                    @else
                                        <span class="badge badge-expired">Expired</span>
                                    @endif
                                </div>
                            </div>

                            @if($isFound && $item->custody_location)
                                <div class="media-overlay-bottom">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6EE7B7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                    <span>{{ $item->custody_location }}</span>
                                </div>
                            @endif
                        </a>

                        <div class="card-quick-actions">
                            <button type="button"
                                    class="card-float-btn"
                                    data-quick-view
                                    title="Quick Preview"
                                    aria-label="Quick Preview {{ $item->title }}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            @if(auth()->user()->hasRole(['user', 'security']))
                                <button type="button"
                                        class="card-float-btn bookmark-btn"
                                        data-bookmark-btn
                                        data-item-id="{{ $item->id }}"
                                        data-item-title="{{ $item->title }}"
                                        data-item-type="{{ $item->type }}"
                                        data-item-url="{{ route('items.show', $item) }}"
                                        title="Save to Watchlist"
                                        aria-label="Save {{ $item->title }} to Watchlist"
                                        aria-pressed="false">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="post-body">
                        <div class="post-category">{{ $item->category->name }}</div>
                        <h3 class="post-title">
                            <a href="{{ route('items.show', $item) }}">{{ $item->title }}</a>
                        </h3>
                        <p class="post-desc">{{ $item->description }}</p>

                        <div class="post-meta">
                            <span>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                </svg>
                                {{ $item->building }}
                            </span>
                            <span>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                {{ $item->event_date->format('d M Y') }}
                            </span>
                        </div>

                        @if($item->isDueForExtendedShelf() && auth()->user()->isSecurity())
                            <span class="badge badge-warning" style="align-self: flex-start;">Move to Extended Shelf</span>
                        @endif

                        @if($item->tags->isNotEmpty())
                            <div class="post-tags" style="margin-top: auto; padding-top: 4px;">
                                @foreach($item->tags as $tag)
                                    <a href="{{ route('dashboard', ['type' => $type, 'q' => '#' . $tag->name]) }}" class="tag-pill">#{{ $tag->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="post-footer">
                        <div class="post-author">
                            <span class="ig-avatar is-sm">{{ $authorInitials ?: 'U' }}</span>
                            <div style="min-width: 0;">
                                <div class="post-author-name">
                                    <span>{{ $item->user->name }}</span>
                                    @if($item->user->isSecurity())
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="#1466EA" stroke="#FFFFFF" stroke-width="2" style="flex-shrink: 0;">
                                            <title>Verified Security</title>
                                            <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4" fill="none"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="caption" style="font-size: 11.5px;">{{ $item->created_at->diffForHumans() }}</div>
                            </div>
                        </div>

                        <div class="post-stats">
                            @if(auth()->user()->hasRole(['user', 'security']))
                                <a href="{{ route('messages.index', ['item' => $item->id]) }}" class="stat-btn" title="Messages">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    {{ $item->messages_count }}
                                </a>
                            @endif

                            @if($isFound)
                                <a href="{{ route('items.show', $item) }}" class="stat-btn" title="Claims">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                    {{ $item->claims_count }}
                                </a>
                            @endif

                            <a href="{{ route('items.show', $item) }}" class="btn btn-primary btn-sm btn-action-arrow" style="margin-left: 6px;">
                                {{ $isFound && auth()->user()->isUser() && $item->isClaimable() && $item->user_id !== auth()->id() ? 'Claim' : 'View' }}
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div style="margin-top: 24px;">
            {{ $items->links() }}
        </div>
    @endif
    </div>
@endsection
