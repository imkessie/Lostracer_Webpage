@extends('layouts.app')

@section('judul', 'Buat Laporan Barang')

@section('konten')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0; color: var(--primary-navy);">Buat Laporan Barang</h2>
        <a href="/dashboard" style="text-decoration: none; color: #64748B; font-weight: 600;">&larr; Kembali</a>
    </div>

    <!-- enctype="multipart/form-data" wajib ada untuk upload berkas foto -->
    <form action="/items" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label>Jenis Laporan</label>
            <select name="type" required>
                <option value="lost" {{ old('type') == 'lost' ? 'selected' : '' }}>Kehilangan Barang (Lost)</option>
                <option value="found" {{ old('type') == 'found' ? 'selected' : '' }}>Menemukan Barang (Found)</option>
            </select>
        </div>

        <div class="form-group">
            <label>Kategori Barang</label>
            <select name="category_id" required>
                <option value="">-- Pilih Kategori --</option>
                @foreach($categories as $kat)
                    <option value="{{ $kat->id }}" {{ old('category_id') == $kat->id ? 'selected' : '' }}>
                        {{ $kat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Nama / Judul Barang</label>
            <input type="text" name="title" value="{{ old('title') }}" placeholder="Contoh: Dompet Kulit Cokelat / Tumbler Corkcicle Biru" required>
        </div>

        <div class="form-group">
            <label>Deskripsi Detail & Ciri Khusus</label>
            <textarea name="description" rows="4" placeholder="Jelaskan warna, stiker, isi di dalam dompet, atau tanda khusus lainnya..." required>{{ old('description') }}</textarea>
        </div>

        <div style="display: flex; gap: 16px;">
            <div class="form-group" style="flex: 1;">
                <label>Gedung Kampus</label>
                <select name="building" required>
                    <option value="">-- Pilih Gedung --</option>
                    <option value="Building A" {{ old('building') == 'Building A' ? 'selected' : '' }}>Building A</option>
                    <option value="Building B" {{ old('building') == 'Building B' ? 'selected' : '' }}>Building B</option>
                    <option value="Building C" {{ old('building') == 'Building C' ? 'selected' : '' }}>Building C</option>
                    <option value="Building D" {{ old('building') == 'Building D' ? 'selected' : '' }}>Building D</option>
                    <option value="Perpustakaan UPH" {{ old('building') == 'Perpustakaan UPH' ? 'selected' : '' }}>Perpustakaan UPH</option>
                    <option value="Food Junction" {{ old('building') == 'Food Junction' ? 'selected' : '' }}>Food Junction / Kantin</option>
                    <option value="Lainnya" {{ old('building') == 'Lainnya' ? 'selected' : '' }}>Area Luar / Gedung Lain</option>
                </select>
            </div>

            <div class="form-group" style="flex: 1;">
                <label>Detail Lantai / Ruangan</label>
                <input type="text" name="floor_detail" value="{{ old('floor_detail') }}" placeholder="Contoh: Lantai 3, R302 atau Dekat Lift">
            </div>
        </div>

        <div class="form-group">
            <label>Tanggal Kejadian</label>
            <input type="date" name="event_date" value="{{ old('event_date', date('Y-m-d')) }}" required>
        </div>

        <div class="form-group">
            <label>Foto Barang (Opsional, Maks 2MB)</label>
            <input type="file" name="photo" accept="image/*">
        </div>

        <div class="form-group">
            <label>Tagar / Hashtag (Pisahkan dengan tanda koma)</label>
            <input type="text" name="tags" value="{{ old('tags') }}" placeholder="Contoh: tumbler, biru, stanley, meja-dosen">
            <small style="color: #64748B;">Tagar memudahkan civitas lain mencari laporan lewat kotak pencarian.</small>
        </div>

        <div class="form-group" style="margin-top: 10px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal;">
                <input type="checkbox" name="is_sensitive" value="1" {{ old('is_sensitive') ? 'checked' : '' }}>
                <span><strong>Sensor Foto Dokumen Pribadi (KTM / KTP / SIM):</strong> Aktifkan efek blur untuk melindungi NIK/NIM dari publik.</span>
            </label>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 24px 0;">

        <button type="submit" class="btn-primary" style="padding: 12px 24px;">Kirim Laporan</button>
    </form>
@endsection