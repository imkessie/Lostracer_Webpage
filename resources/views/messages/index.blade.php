@extends('layouts.app')

@section('konten')
<div class="feed-container" style="max-width: 800px; margin: 0 auto;">

    <h2 style="margin-bottom: 20px; font-size: 22px; color: #0f172a;">💬 Your Messages & Conversations</h2>

    <div class="post-card" style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
        @forelse($conversations as $item)
            @php 
                $lastMsg = $item->messages->first(); 
                $unreadCount = $item->messages->where('receiver_id', auth()->id())->where('is_read', false)->count();
            @endphp
            
            <a href="{{ route('messages.show', $item->id) }}" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: inherit; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                
                <div style="display: flex; align-items: center; gap: 14px;">
                    <!-- Item Photo Preview or Icon -->
                    @if(!empty($item->photo_path))
                        <img src="{{ asset('storage/' . str_replace('\\', '/', $item->photo_path)) }}" alt="{{ $item->title }}" style="width: 48px; height: 48px; border-radius: 8px; object-fit: cover;">
                    @else
                        <div style="width: 48px; height: 48px; border-radius: 8px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 20px;">📦</div>
                    @endif

                    <div>
                        <strong style="font-size: 15px; color: #1e293b; display: block; margin-bottom: 2px;">{{ $item->title }}</strong>
                        <p style="font-size: 13px; color: #64748b; margin: 0; display: flex; align-items: center; gap: 6px;">
                            <span>{{ $lastMsg ? $lastMsg->message : 'No messages yet' }}</span>
                        </p>
                    </div>
                </div>

                <div style="text-align: right;">
                    <span style="font-size: 11px; color: #94a3b8; display: block; margin-bottom: 4px;">
                        {{ $lastMsg ? $lastMsg->created_at->diffForHumans() : '' }}
                    </span>

                    @if($unreadCount > 0)
                        <span style="background: #ef4444; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: 600;">
                            {{ $unreadCount }} New
                        </span>
                    @endif
                </div>

            </a>
        @empty
            <div style="padding: 40px; text-align: center; color: #94a3b8;">
                <span style="font-size: 32px; display: block; margin-bottom: 8px;">📥</span>
                <p style="margin: 0; font-size: 14px;">No active conversations yet.</p>
            </div>
        @endforelse
    </div>

</div>
@endsection