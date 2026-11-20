@extends('layouts.app')

@section('title', 'Audit Logs & Summary')

@section('konten')
<div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 14px; margin-bottom: 22px;">
    <div>
        <h1 class="page-title">Audit Logs</h1>
        <p style="margin: 4px 0 0; color: var(--text-muted);">
            Activity log for claims, custody transfers, handovers, and moderation.
        </p>
    </div>

    <a href="{{ route('audit.export') }}" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Export CSV
    </a>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <span class="kpi-label">Total Posts</span>
        <span class="kpi-value" style="color: var(--primary-navy);">{{ $summary['total_items'] }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Active Found Items in Custody</span>
        <span class="kpi-value" style="color: var(--emerald-found);">{{ $summary['open_found'] }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Returned Items</span>
        <span class="kpi-value">{{ $summary['returned_items'] }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Expired (6 Months)</span>
        <span class="kpi-value" style="color: var(--text-muted);">{{ $summary['expired_items'] }}</span>
    </div>
</div>

<div class="card-surface" style="margin-bottom: 20px; padding: 14px 18px;">
    <form action="{{ route('audit.index') }}" method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="text" name="q" value="{{ $q }}" data-shortcut-search placeholder="Search notes, actor name, or item title..." style="flex: 1; min-width: 220px;">
        <select id="action" name="action" aria-label="Filter action" data-auto-submit style="max-width: 240px;">
            <option value="">All Audit Actions</option>
            @foreach($actions as $action)
                <option value="{{ $action }}" {{ $actionFilter === $action ? 'selected' : '' }}>{{ $action }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
        @if($actionFilter !== '' || $q !== '')
            <a href="{{ route('audit.index') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </form>
</div>

<div class="card-surface">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Post</th>
                    <th>Actor</th>
                    <th>Notes &amp; Handover Photo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $badgeClass = 'badge-claimed';
                        if ($log->action === 'HANDOVER_COMPLETED') $badgeClass = 'badge-found-open';
                        elseif (in_array($log->action, ['POST_DELETED', 'USER_BLOCKED', 'CLAIM_REJECTED'], true)) $badgeClass = 'badge-danger';
                        elseif (in_array($log->action, ['ITEM_EXPIRED', 'ITEM_DISPOSED'], true)) $badgeClass = 'badge-expired';
                    @endphp
                    <tr>
                        <td style="white-space: nowrap; font-variant-numeric: tabular-nums;">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td><button type="button" class="badge {{ $badgeClass }} action-filter-badge" data-filter-action="{{ $log->action }}" title="Filter by {{ $log->action }}" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10.5px; cursor: pointer;">{{ $log->action }}</button></td>
                        <td>
                            @if($log->item)
                                <a href="{{ route('items.show', $log->item) }}" style="font-weight: 600; color: var(--primary-navy); text-decoration: none;">
                                    {{ $log->item->title }}
                                </a>
                            @else
                                <span class="caption">&mdash;</span>
                            @endif
                        </td>
                        <td>{{ $log->user?->name ?? '-' }}</td>
                        <td>
                            {{ $log->notes }}
                            @if($log->photo_path)
                                <div style="margin-top: 6px;">
                                    <a href="{{ route('audit.photo', $log) }}" target="_blank" class="btn btn-outline btn-sm">
                                        View Handover Photo
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 28px;">No audit records yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 16px;">
        {{ $logs->links() }}
    </div>
</div>
@endsection
