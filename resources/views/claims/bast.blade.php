@extends('layouts.app')

@section('title', 'BAST #' . $claim->id)

@section('konten')
<div class="no-print" style="max-width: 780px; margin: 0 auto 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
    <a href="{{ route('claims.index') }}" class="btn btn-outline btn-sm">&larr; Back to Claims Queue</a>
    <button type="button" data-print class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
        </svg>
        Print BAST Slip
    </button>
</div>

<div class="card-surface bast-paper" style="max-width: 780px; margin: 0 auto; border: 2px solid var(--text-dark); padding: 32px;">
    <div style="border-bottom: 2px solid var(--text-dark); padding-bottom: 16px; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
        <div>
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--primary-navy);">
                Universitas Pelita Harapan &bull; Corporate Facilities &amp; Security
            </div>
            <h1 style="margin: 4px 0 0; font-size: 20px; color: var(--text-dark);">OFFICIAL HANDOVER RELEASE SLIP (BAST)</h1>
        </div>
        <div style="text-align: right; font-size: 12px; background: var(--canvas); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color);">
            <strong>BAST No:</strong> BAST-{{ str_pad((string) $claim->id, 5, '0', STR_PAD_LEFT) }}<br>
            <strong>Pickup Code:</strong> {{ $claim->pickup_code }}
        </div>
    </div>

    <p style="margin: 0 0 18px; font-size: 13.5px;">
        On <strong>{{ $claim->handover_at?->format('d M Y, H:i') }} WIB</strong>, the physical item listed below was officially verified and handed over through the Lostracer UPH system:
    </p>

    <div class="table-wrap" style="border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 22px;">
        <table class="data-table">
            <tr>
                <th style="width: 34%;">Item Name</th>
                <td><strong>{{ $claim->item->title }}</strong> ({{ $claim->item->category->name }})</td>
            </tr>
            <tr>
                <th>Last Custody Location</th>
                <td>{{ $claim->item->custody_location ?? '-' }}</td>
            </tr>
            <tr>
                <th>Recipient (Verified Owner)</th>
                <td>{{ $claim->user->name }} ({{ $claim->user->email }})</td>
            </tr>
            <tr>
                <th>Releasing Security Officer</th>
                <td>{{ $handoverLog?->user?->name ?? $claim->verifier?->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Handover Timestamp</th>
                <td>{{ $claim->handover_at?->format('d M Y, H:i') }} WIB</td>
            </tr>
            <tr>
                <th>Notes &amp; Photo Reference</th>
                <td>
                    {{ $handoverLog?->notes ?? '-' }}
                    @if($handoverLog?->photo_path)
                        <div style="margin-top: 8px;" class="no-print">
                            <a href="{{ route('audit.photo', $handoverLog) }}" target="_blank" class="btn btn-outline btn-sm">
                                View Private Handover Photo
                            </a>
                        </div>
                        <div class="caption" style="margin-top: 4px;">File Ref: {{ basename($handoverLog->photo_path) }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 36px; margin-top: 38px; text-align: center;">
        <div>
            <p style="margin: 0 0 60px; font-size: 13px;">Recipient / Item Owner,</p>
            <div style="border-top: 1px solid var(--text-dark); padding-top: 6px; font-weight: 600;">
                {{ $claim->user->name }}
            </div>
        </div>
        <div>
            <p style="margin: 0 0 60px; font-size: 13px;">UPH Security Officer,</p>
            <div style="border-top: 1px solid var(--text-dark); padding-top: 6px; font-weight: 600;">
                {{ $handoverLog?->user?->name ?? $claim->verifier?->name ?? 'Security Officer' }}
            </div>
        </div>
    </div>
</div>
@endsection
