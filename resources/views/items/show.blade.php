@extends('layouts.app')

@section('judul', 'Details')

@section('konten')
<div class="feed-container">

    <!-- Top Navigation / Back Button -->
    <div style="margin-bottom: 20px;">
        <a href="{{ route('dashboard') }}" style="display: inline-flex; align-items: center; gap: 8px; color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">
            ← Back to Feed
        </a>
    </div>

    <!-- Main Detail Card -->
    <div class="post-card">
        
        <!-- Card Header: User Info & Status Badge -->
        <div class="post-card-header">
            <div class="user-info">
                <div class="avatar">
                    {{ strtoupper(substr($item->user->name ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <strong class="user-name">{{ $item->user->name ?? 'Anonymous' }}</strong>
                    <span class="post-meta">Reported on {{ $item->created_at->format('M d, Y • H:i') }}</span>
                </div>
            </div>
            
            <span class="status-badge {{ $item->type === 'found' ? 'badge-found' : 'badge-lost' }}">
                {{ strtoupper($item->type) }} — {{ strtoupper($item->status) }}
            </span>
        </div>

        <!-- Full Resolution Image Section -->
        @if(!empty($item->photo_path))
            <div class="post-image-container" style="padding: 16px; background: #0f172a; text-align: center;">
                @if($item->is_sensitive && auth()->user()?->role !== 'security_officer')
                    <!-- Privacy Masking for Sensitive Items (FR-07) -->
                    <div style="position: relative; overflow: hidden; border-radius: 8px;">
                        <img 
                            src="{{ asset('storage/' . str_replace('\\', '/', $item->photo_path)) }}" 
                            alt="{{ $item->title }}" 
                            class="post-image"
                            style="filter: blur(12px); max-height: 450px; width: 100%; object-fit: contain;"
                        >
                        <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.6); color: white; padding: 20px;">
                            <span style="font-size: 24px; margin-bottom: 8px;">🔒</span>
                            <strong style="font-size: 16px; margin-bottom: 4px;">Protected Sensitive Document</strong>
                            <p style="font-size: 13px; color: #cbd5e1; margin: 0; text-align: center;">Photo blurred for owner privacy. Full unblurred image is accessible only to verified Campus Security Officers.</p>
                        </div>
                    </div>
                @else
                    <!-- Unblurred Image -->
                    <img 
                        src="{{ asset('storage/' . str_replace('\\', '/', $item->photo_path)) }}" 
                        alt="{{ $item->title }}" 
                        class="post-image"
                        style="max-height: 450px; width: 100%; object-fit: contain; border-radius: 8px;"
                    >
                @endif
            </div>
        @endif

        <!-- Card Body: Full Info & Metadata -->
        <div class="post-card-body" style="padding: 24px;">
            <h1 class="post-title" style="font-size: 24px; margin-bottom: 12px;">{{ $item->title }}</h1>
            
            <!-- Metadata Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; color: #334155;">
                <div>📍 <strong>Building:</strong> {{ $item->building ?? 'N/A' }}</div>
                <div>🚪 <strong>Specific Room / Floor:</strong> {{ $item->floor_detail ?? 'Not specified' }}</div>
                <div>📅 <strong>Incident Date:</strong> {{ $item->event_date ?? $item->created_at->format('Y-m-d') }}</div>
                <div>📁 <strong>Category:</strong> {{ $item->category->name ?? 'General' }}</div>
            </div>

            <!-- Tags -->
            @if($item->tags && $item->tags->count() > 0)
                <div class="tag-list" style="margin-bottom: 20px;">
                    @foreach($item->tags as $tag)
                        <a href="{{ route('dashboard', ['q' => '#' . $tag->name]) }}" class="tag-link" style="font-size: 13px;">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            <!-- Full Description -->
            <h3 style="font-size: 14px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 8px;">Detailed Description</h3>
            <p class="post-description" style="font-size: 15px; line-height: 1.6; color: #1e293b; margin: 0;">
                {{ $item->description }}
            </p>
        </div>

        <!-- Card Footer: Claim Submission & Communication Actions -->
        <div class="post-card-footer" style="padding: 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 16px; align-items: stretch;">
            
            <!-- Claim Form Section (Visible for OPEN Found Items) -->
            @if($item->type === 'found' && $item->status === 'open' && auth()->id() !== $item->user_id)
                <div style="background: #ffffff; border: 1px solid #cbd5e1; padding: 20px; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 6px 0; font-size: 16px; color: #0f172a;">Is this item yours? Submit a Claim</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">Provide verified proof of ownership (e.g., serial number, hidden marks, or purchase receipt) for security officer verification[cite: 11, 12, 16].</p>
                    
                    <form action="{{ route('claims.store', $item->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div style="margin-bottom: 14px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Proof Description:</label>
                            <textarea 
                                name="proof_description" 
                                rows="3" 
                                placeholder="State unique characteristics, serial numbers, wallpaper image, or exact contents inside..." 
                                required 
                                style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px; outline: none; box-sizing: border-box;"
                            ></textarea>
                        </div>
                        
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Upload Proof File / Photo (Encrypted):</label>
                            <input 
                                type="file" 
                                name="proof_file" 
                                accept="image/*,.pdf" 
                                required 
                                style="font-size: 13px; color: #475569;"
                            >
                        </div>

                        <button type="submit" class="btn-details" style="width: 100%; text-align: center; padding: 12px; font-size: 14px; border: none; cursor: pointer;">
                            🔒 Submit Ownership Claim
                        </button>
                    </form>
                </div>
            @endif

            <!-- In-App Direct Messaging Action (FR-05) -->
            @if(auth()->id() !== $item->user_id)
                
                <a href="{{ route('messages.show', $item->id) }}" style="text-align: center; background: #1e3a8a; color: white; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px;">
                    💬 Send In-App Message to {{ $item->type === 'found' ? 'Finder' : 'Reporter' }}
                </a>
                @endif

            

        </div>

    </div>

</div>
@endsection