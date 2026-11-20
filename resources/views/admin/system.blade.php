@extends('layouts.app')

@section('title', 'System & Domain Config')

@section('konten')
<div style="margin-bottom: 22px;">
    <h1 class="page-title">System &amp; Domain Configuration</h1>
    <p style="margin: 4px 0 0; color: var(--text-muted);">
        Allowed campus email domains, custody points, and infrastructure status.
    </p>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <span class="kpi-label">Registered Accounts</span>
        <span class="kpi-value" style="color: var(--primary-navy);">{{ $stats['total_users'] }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Blocked Accounts</span>
        <span class="kpi-value" style="color: var(--danger);">{{ $stats['blocked_users'] }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Total Posts</span>
        <span class="kpi-value">{{ $stats['total_items'] }}</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-label">Expired Posts</span>
        <span class="kpi-value" style="color: var(--text-muted);">{{ $stats['expired_items'] }}</span>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 20px;">
    <div class="card-surface">
        <h2 class="section-title" style="color: var(--primary-navy); margin-bottom: 14px;">Infrastructure Health</h2>
        <div class="table-wrap">
            <table class="data-table">
                <tr>
                    <th>Runtime</th>
                    <td>PHP {{ $health['php_version'] }} &bull; Laravel {{ $health['laravel_version'] }}</td>
                </tr>
                <tr>
                    <th>Database ({{ $health['db_connection'] }})</th>
                    <td>
                        @if($health['db_healthy'])
                            <span class="badge badge-found-open">Connected</span>
                        @else
                            <span class="badge badge-danger">Error: {{ $health['db_error'] }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>PHP GD Extension</th>
                    <td>
                        @if($health['gd_loaded'])
                            <span class="badge badge-found-open">Active (Server-Side Blur)</span>
                        @else
                            <span class="badge badge-warning">Inactive (Locked Placeholder Fallback)</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Public Storage Symlink</th>
                    <td>
                        @if($health['storage_link_exists'])
                            <span class="badge badge-found-open">Available</span>
                        @else
                            <span class="badge badge-danger">Missing (run php artisan storage:link)</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card-surface">
        <h2 class="section-title" style="color: var(--primary-navy); margin-bottom: 14px;">Domain &amp; Retention Rules</h2>
        <div class="table-wrap">
            <table class="data-table">
                <tr>
                    <th style="width: 38%;">Allowed Email Domains</th>
                    <td>
                        @foreach($config['allowed_email_domains'] as $domain)
                            <span class="badge badge-claimed">&#64;{{ $domain }}</span>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <th>Extended Shelf Move</th>
                    <td><strong>{{ $config['retention']['shelf_move_months'] }} months</strong></td>
                </tr>
                <tr>
                    <th>Maximum Retention</th>
                    <td><strong>{{ $config['retention']['expiration_months'] }} months</strong></td>
                </tr>
                <tr>
                    <th>Pickup Window &amp; Attempts</th>
                    <td><strong>{{ $config['retention']['pickup_window_days'] }} days</strong> &bull; Max <strong>{{ $config['retention']['max_claim_attempts'] }}x</strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div class="card-surface">
    <h2 class="section-title" style="color: var(--primary-navy); margin-bottom: 12px;">Configured Custody Locations (config/lostracer.php)</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px;">
        @foreach($config['custody_locations'] as $location)
            <div style="padding: 10px 12px; border-radius: 8px; background: var(--canvas); border: 1px solid var(--border-color); font-size: 13px;">
                {{ $location }}
            </div>
        @endforeach
    </div>
</div>
@endsection
