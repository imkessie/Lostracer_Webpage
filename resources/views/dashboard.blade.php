@extends('layouts.app')

@section('judul', 'Dashboard')

@section('konten')
    <h2 style="margin-top: 0; color: var(--primary-navy);">Selamat Datang, {{ auth()->user()->name }}!</h2>
    <p>Status Peran Akun: <strong>{{ strtoupper(auth()->user()->role) }}</strong></p>
    <p>Email: {{ auth()->user()->email }}</p>

    <div style="margin-top: 24px; padding: 16px; background-color: #F1F5F9; border-radius: 8px;">
        <h4 style="margin-top: 0;">Sistem Pelaporan Barang Kampus</h4>
        <p style="margin-bottom: 0;">Modul katalog barang hilang & temuan (*Dual Feed*) akan kita integrasikan di tahap selanjutnya.</p>
    </div>
@endsection