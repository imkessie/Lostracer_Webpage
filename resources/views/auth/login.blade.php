@extends('layouts.app')

@section('judul', 'Masuk Civitas')

@section('konten')
    <h2 style="margin-top: 0; color: var(--primary-navy);">Login Lostracer</h2>
    <p style="color: #64748B; margin-bottom: 24px;">Gunakan akun resmi kampus UPH Anda untuk masuk.</p>

    <form action="/login" method="POST">
        @csrf
        <div class="form-group">
            <label>Email Kampus</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@student.uph.edu atau @uph.edu" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%;">Masuk ke Sistem</button>
    </form>

    <p style="text-align: center; margin-top: 20px; font-size: 14px;">
        Belum punya akun? <a href="/register" style="color: var(--primary-navy); font-weight: 600;">Daftar di sini</a>
    </p>
@endsection