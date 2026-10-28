@extends('layouts.app')

@section('judul', 'Daftar Akun Civitas')

@section('konten')
    <h2 style="margin-top: 0; color: var(--primary-navy);">Daftar Akun Lostracer</h2>
    <p style="color: #64748B; margin-bottom: 24px;">Khusus mahasiswa, staf, dan dosen aktif Universitas Pelita Harapan.</p>

    <form action="/register" method="POST">
        @csrf
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Kessie Sengupta" required>
        </div>

        <div class="form-group">
            <label>Email Resmi Institusi</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="nim@student.uph.edu" required>
            <small style="color: #64748B;">Hanya menerima domain @student.uph.edu atau @uph.edu</small>
        </div>

        <div class="form-group">
            <label>Password (Minimal 6 karakter)</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Konfirmasi Password</label>
            <input type="password" name="password_confirmation" required>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%;">Buat Akun</button>
    </form>

    <p style="text-align: center; margin-top: 20px; font-size: 14px;">
        Sudah memiliki akun? <a href="/login" style="color: var(--primary-navy); font-weight: 600;">Login di sini</a>
    </p>
@endsection