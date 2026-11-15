@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
<div class="feed-container">

    <!-- Search & Tabs Card -->
    <div class="feed-header-card">
        <form action="{{ route('dashboard') }}" method="GET" class="search-form">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search title, location, or #tag..." class="search-input">
            <button type="submit" class="btn-search">Search</button>
        </form>

        <div class="tab-container">
            <a href="{{ route('dashboard', ['type' => 'found', 'q' => $q]) }}" class="tab-link {{ $type === 'found' ? 'active-found' : '' }}">
                Found Items ({{ $totalFound }})
            </a>
            <a href="{{ route('dashboard', ['type' => 'lost', 'q' => $q]) }}" class="tab-link {{ $type === 'lost' ? 'active-lost' : '' }}">
                Lost Items ({{ $totalLost }})
            </a>
        </div>
    </div>

    <!-- Feed Cards -->
    <div class="post-list">
        @forelse ($items as $item)
            <div class="post-card">
                
                <!-- Card Header -->
                <div class="post-card-header">
                    <div class="user-info">
                        <div class="avatar">
                            {{ strtoupper(substr($item->user->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <strong class="user-name">{{ $item->user->name ?? 'Anonymous' }}</strong>
                            <span class="post-meta">{{ $item->building ?? 'Location N/A' }} • {{ $item->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    
                    <span class="status-badge {{ $item->type === 'found' ? 'badge-found' : 'badge-lost' }}">
                        {{ $item->type }}
                    </span>
                </div>

                <!-- Card Image -->
                @if(!empty($item->photo_path))
                    <div class="post-image-container">
                        <img 
                            src="{{ asset('storage/' . $item->photo_path) }}"
                            alt="{{ $item->title }}" 
                            class="post-image"
                        >
                    </div>
                @endif

                <!-- Card Body -->
                <div class="post-card-body">
                    <h3 class="post-title">{{ $item->title }}</h3>
                    <p class="post-description">{{ $item->description }}</p>

                    @if($item->tags && $item->tags->count() > 0)
                        <div class="tag-list">
                            @foreach($item->tags as $tag)
                                <a href="{{ route('dashboard', ['q' => '#' . $tag->name, 'type' => $type]) }}" class="tag-link">
                                    #{{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Card Footer -->
                <div class="post-card-footer">
                    <span class="category-label">Category: <strong>{{ $item->category->name ?? 'General' }}</strong></span>
                    <a href="{{ route('items.show', $item->id) }}" class="btn-details">
                        View Details / Claim
                    </a>
                </div>

            </div>
        @empty
            <div class="empty-state">
                No {{ $type }} items reported yet.
            </div>
        @endforelse
    </div>

</div>
@endsection