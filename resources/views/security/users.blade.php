@extends('layouts.app')

@section('title', 'User Moderation')

@section('konten')
<div style="margin-bottom: 22px;">
    <h1 class="page-title">User Account Moderation</h1>
    <p style="margin: 4px 0 0; color: var(--text-muted);">
        Manage campus member access and account suspensions.
    </p>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <span class="kpi-label">Total User Accounts</span>
        <span class="kpi-value">{{ $totalUsers }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Active Accounts</span>
        <span class="kpi-value" style="color: var(--emerald-found);">{{ $activeCount }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Blocked Accounts</span>
        <span class="kpi-value" style="color: var(--danger);">{{ $blockedCount }}</span>
    </div>
</div>

<div class="card-surface" style="margin-bottom: 18px; padding: 14px 18px;">
    <form action="{{ route('security.users') }}" method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        <input type="text" name="q" value="{{ $q }}" data-shortcut-search placeholder="Search by student name or @uph email..." style="flex: 1; min-width: 220px;">
        <select name="status" data-auto-submit style="max-width: 180px;">
            <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
            <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active Only</option>
            <option value="blocked" {{ $statusFilter === 'blocked' ? 'selected' : '' }}>Blocked Only</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        @if($q !== '' || $statusFilter !== 'all')
            <a href="{{ route('security.users') }}" class="btn btn-outline btn-sm">Reset</a>
        @endif
    </form>
</div>

<div class="card-surface">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Activity</th>
                    <th>Status</th>
                    <th>Moderation Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $account)
                    <tr>
                        <td>
                            <strong>{{ $account->name }}</strong>
                            <div class="caption">{{ $account->email }}</div>
                        </td>
                        <td>{{ $account->items_count }} posts &bull; {{ $account->claims_count }} claims</td>
                        <td>
                            @if($account->is_blocked)
                                <span class="badge badge-danger">BLOCKED</span>
                            @else
                                <span class="badge badge-found-open">ACTIVE</span>
                            @endif
                        </td>
                        <td>
                            @if($account->is_blocked)
                                <form action="{{ route('security.users.unblock', $account) }}"
                                      method="POST"
                                      data-confirm="Unblock account {{ $account->email }}?"
                                      data-confirm-title="Confirm Unblock"
                                      data-confirm-label="Unblock"
                                      style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Reason to unblock..." required style="max-width: 240px;">
                                    <button type="submit" class="btn btn-success btn-sm">Unblock</button>
                                </form>
                            @else
                                <form action="{{ route('security.users.block', $account) }}"
                                      method="POST"
                                      data-confirm="Block {{ $account->email }}? Open posts will be hidden and pending claims automatically withdrawn."
                                      data-confirm-title="Confirm Account Block"
                                      data-confirm-variant="danger"
                                      data-confirm-label="Yes, Block Account"
                                      style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Written reason (required)..." required style="max-width: 240px;">
                                    <button type="submit" class="btn btn-danger btn-sm">Block Account</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 28px;">No matching user accounts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
