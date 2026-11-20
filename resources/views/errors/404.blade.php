@extends('layouts.app')

@section('title', 'Page Not Found')

@section('konten')
<div style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div class="card-surface" style="max-width: 480px; width: 100%; text-align: center; padding: 38px 28px;">
        <div style="width: 56px; height: 56px; border-radius: 14px; background: var(--primary-light); color: var(--primary-navy); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
        </div>
        <div class="caption" style="font-weight: 700; color: var(--primary-navy); letter-spacing: 0.6px; text-transform: uppercase; margin-bottom: 6px;">Error 404</div>
        <h1 class="page-title" style="margin-bottom: 8px;">Post or Page Not Found</h1>
        <p style="margin: 0 0 22px; color: var(--text-muted);">
            The item post or page you requested is unavailable, hidden, or has been removed.
        </p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">
            {{ auth()->check() ? 'Back to Posts' : 'Back to Sign In' }}
        </a>
    </div>
</div>
@endsection
