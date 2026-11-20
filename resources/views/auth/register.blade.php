@extends('layouts.app')

@section('title', 'Register Campus Account')

@section('konten')
<div class="auth-shell">
    {{-- Visual panel --}}
    <aside class="auth-visual">
        <a href="{{ route('login') }}" class="auth-brand" aria-label="Lostracer - UPH Campus">
            <span class="auth-brand-lockup">
                @include('partials.brand-wordmark', ['class' => 'brand-wordmark auth-wordmark'])
                <span class="auth-brand-sub">UPH Campus</span>
            </span>
        </a>

        <div>
            <h2 class="auth-motto">Don't replace it,<br>trace it.</h2>
            <p class="auth-motto-sub">Universitas Pelita Harapan &middot; Lost &amp; Found</p>
        </div>
    </aside>

    {{-- Form panel --}}
    <section class="auth-panel">
        <button type="button" class="theme-toggle-btn auth-theme-toggle" data-theme-toggle aria-label="Toggle dark mode" title="Toggle theme">
            <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
            <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
        </button>
        <div class="auth-form-wrap">
            <div class="auth-mobile-brand" aria-label="Lostracer">
                @include('partials.brand-wordmark', ['class' => 'brand-wordmark'])
            </div>

            <h1 class="auth-title">Create Lostracer Account</h1>
            <p class="auth-subtitle">Sign up with your @student.uph.edu or @uph.edu email.</p>

            <form action="{{ route('register') }}" method="POST" data-register-form data-allowed-domains="{{ implode(',', config('lostracer.allowed_email_domains')) }}">
                @csrf
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. John Doe" autocomplete="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Campus Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="e.g. john.doe@student.uph.edu / @uph.edu" autocomplete="email" required>
                    <span class="field-feedback" data-email-feedback></span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 6px;">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div style="position: relative;">
                            <input id="password" type="password" name="password" placeholder="Min. 6 characters" autocomplete="new-password" minlength="6" required style="padding-right: 60px;">
                            <button type="button" class="password-toggle" data-password-toggle="password" aria-pressed="false">Show</button>
                        </div>
                        <div class="pw-strength-wrap" data-pw-strength-wrap hidden>
                            <div class="pw-strength-bars" data-pw-bars="0">
                                <span class="pw-strength-bar"></span>
                                <span class="pw-strength-bar"></span>
                                <span class="pw-strength-bar"></span>
                            </div>
                            <span class="pw-strength-label" data-pw-label></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password</label>
                        <div style="position: relative;">
                            <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat password" autocomplete="new-password" minlength="6" required style="padding-right: 60px;">
                            <button type="button" class="password-toggle" data-password-toggle="password_confirmation" aria-pressed="false">Show</button>
                        </div>
                        <span class="field-feedback" data-password-match></span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    Create Account
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <p class="auth-switch">
                Already have an account?
                <a href="{{ route('login') }}">Sign in</a>
            </p>
        </div>

        <div class="auth-legal">&copy; Lostracer</div>
    </section>
</div>
@endsection
