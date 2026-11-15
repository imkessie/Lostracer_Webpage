@extends('layouts.app')

@section('konten')
<div class="feed-container" style="max-width: 700px; margin: 0 auto;">

    <!-- Back Button -->
    <div style="margin-bottom: 16px;">
        <a href="{{ route('items.show', $item->id) }}" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">
            ← Back to Item Details
        </a>
    </div>

    <!-- Chat Card Box -->
    <div class="post-card" style="display: flex; flex-direction: column; height: 600px; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
        
        <!-- Header -->
        <div style="padding: 16px 20px; background: #1e3a8a; color: white; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; font-size: 16px;">💬 Chat regarding: {{ $item->title }}</h3>
                <span style="font-size: 12px; opacity: 0.8;">Reporter: {{ $item->user->name ?? 'User' }} • {{ ucfirst($item->type) }} Item</span>
            </div>
        </div>

        <!-- Chat History -->
        <div style="flex: 1; padding: 20px; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 12px;">
            @forelse($messages as $msg)
                @php $isMe = $msg->sender_id === auth()->id(); @endphp
                <div style="display: flex; flex-direction: column; align-items: {{ $isMe ? 'flex-end' : 'flex-start' }};">
                    <span style="font-size: 11px; color: #64748b; margin-bottom: 2px;">
                        {{ $isMe ? 'You' : ($msg->sender->name ?? 'User') }} • {{ $msg->created_at->format('H:i') }}
                    </span>
                    <div style="max-width: 75%; padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.4; 
                                {{ $isMe ? 'background: #2563eb; color: white; border-bottom-right-radius: 2px;' : 'background: #ffffff; color: #1e293b; border: 1px solid #cbd5e1; border-bottom-left-radius: 2px;' }}">
                        {{ $msg->message }}
                    </div>
                </div>
            @empty
                <div style="text-align: center; color: #94a3b8; margin: auto; font-size: 14px;">
                    No messages yet. Start the conversation below!
                </div>
            @endforelse
        </div>

        <!-- Message Input Box -->
        <div style="padding: 16px; background: #ffffff; border-top: 1px solid #e2e8f0;">
            <form action="{{ route('messages.store', $item->id) }}" method="POST" style="display: flex; gap: 10px;">
                @csrf
                <input 
                    type="text" 
                    name="message" 
                    placeholder="Type your message..." 
                    required 
                    autocomplete="off"
                    style="flex: 1; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none;"
                >
                <button type="submit" style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                    Send
                </button>
            </form>
        </div>

    </div>

</div>
@endsection