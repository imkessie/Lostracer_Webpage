@extends('layouts.app')

@section('title', 'Relocate Custody & Correct Post')

@section('konten')
<div style="max-width: 700px; margin: 0 auto;">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}">Posts</a>
        <span>/</span>
        <a href="{{ route('items.show', $item) }}">{{ $item->title }}</a>
        <span>/</span>
        <span>Relocate Custody</span>
    </nav>

    <div class="card-surface">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px;">
            <div>
                <h1 class="page-title" style="font-size: 22px;">Custody Relocation &amp; Correction</h1>
                <p class="caption" style="margin: 4px 0 0;">Every location transfer and flag correction is automatically recorded in the audit log.</p>
            </div>
            <a href="{{ route('items.show', $item) }}" class="btn btn-outline btn-sm">&larr; Back</a>
        </div>

        <div class="alert alert-info">
            <div class="alert-body">
                <strong>Item:</strong> {{ $item->title }}<br>
                <strong>Current Custody Location:</strong> {{ $item->custody_location ?? '-' }}
            </div>
        </div>

        <form action="{{ route('items.location.update', $item) }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="custody_location">New Custody Location</label>
                <select id="custody_location" name="custody_location" required>
                    @foreach($custodyLocations as $location)
                        <option value="{{ $location }}" {{ old('custody_location', $item->custody_location) === $location ? 'selected' : '' }}>
                            {{ $location }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; padding: 14px; border-radius: 10px; background: var(--canvas); border: 1px solid var(--border-color);">
                <label style="display: flex; align-items: center; gap: 10px; font-weight: 400; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="is_sensitive" value="1" {{ old('is_sensitive', $item->is_sensitive) ? 'checked' : '' }} style="width: auto;">
                    <span><strong>Mark as Sensitive Item</strong> (blur photo in public feeds)</span>
                </label>

                <label style="display: flex; align-items: center; gap: 10px; font-weight: 400; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="is_hidden" value="1" {{ old('is_hidden', $item->is_hidden) ? 'checked' : '' }} style="width: auto;">
                    <span><strong>Hide from Public Posts</strong> (e.g. physical item missing or under review)</span>
                </label>
            </div>

            <div class="form-group">
                <label for="notes">Audit Notes</label>
                <textarea id="notes" name="notes" rows="3" placeholder="e.g. Transferred from Building B Security Post to Lost & Found Priority Shelf.">{{ old('notes') }}</textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <a href="{{ route('items.show', $item) }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Save &amp; Record Audit Log</button>
            </div>
        </form>
    </div>
</div>
@endsection
