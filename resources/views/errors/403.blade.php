@extends('layouts.app')

@section('title', 'Access Denied')

@section('konten')
<div style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div class="card-surface" style="max-width: 480px; width: 100%; text-align: center; padding: 38px 28px;">
        <div style="width: 56px; height: 56px; border-radius: 14px; background: var(--rose-light); color: var(--danger); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <div class="caption" style="font-weight: 700; color: var(--danger); letter-spacing: 0.6px; text-transform: uppercase; margin-bottom: 6px;">Error 403</div>
        <h1 class="page-title" style="margin-bottom: 8px;">Access Restricted</h1>
        <p style="margin: 0 0 22px; color: var(--text-muted);">
            {{ $exception->getMessage() ?: 'You do not have permission to access this page or resource.' }}
        </p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">
            {{ auth()->check() ? 'Back to Posts' : 'Back to Sign In' }}
        </a>
    </div>
</div>
@endsection
