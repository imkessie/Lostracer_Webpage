@extends('layouts.app')

@section('title', auth()->user()->isSecurity() ? 'Verification & Handover' : 'My Claims')

@section('konten')
@if(auth()->user()->isSecurity())
    <div style="margin-bottom: 22px;">
        <h1 class="page-title">Verification &amp; Handover</h1>
        <p style="margin: 4px 0 0; color: var(--text-muted);">
            Review ownership claims and complete physical item handovers.
        </p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" data-scroll-to="#section-pending" role="button" tabindex="0">
            <span class="kpi-label">Pending Review</span>
            <span class="kpi-value" style="color: var(--primary-navy);">{{ $pendingClaims->count() }}</span>
        </div>
        <div class="kpi-card" data-scroll-to="#section-ready" role="button" tabindex="0">
            <span class="kpi-label">Ready for Handover</span>
            <span class="kpi-value" style="color: var(--emerald-found);">{{ $readyForHandover->count() }}</span>
        </div>
        <div class="kpi-card" data-scroll-to="#section-history" role="button" tabindex="0">
            <span class="kpi-label">Recent History</span>
            <span class="kpi-value">{{ $recentClaims->count() }}</span>
        </div>
    </div>

    {{-- 1. Pending Claims --}}
    <div id="section-pending" class="card-surface" style="margin-bottom: 22px;">
        <h2 class="section-title" style="color: var(--primary-navy); margin-bottom: 16px;">
            1. Pending Verification ({{ $pendingClaims->count() }})
        </h2>

        @forelse($pendingClaims as $claim)
            <div class="claim-queue-card" style="border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; margin-bottom: 16px; background: var(--canvas);">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <a href="{{ route('items.show', $claim->item) }}" style="font-size: 16px; font-weight: 700; color: var(--primary-navy); text-decoration: none;">
                            {{ $claim->item->title }}
                        </a>
                        <div class="caption" style="margin-top: 3px;">
                            Claimant: <strong>{{ $claim->user->name }}</strong> ({{ $claim->user->email }})
                            &bull; Attempt #{{ $claim->attempt_no }}
                            &bull; Submitted {{ $claim->created_at->format('d M Y H:i') }}
                        </div>
                    </div>
                    <span class="badge badge-claimed">PENDING REVIEW</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 14px;">
                    <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                        <div class="caption" style="font-weight: 600; margin-bottom: 4px;">Claimant Proof Description</div>
                        <div style="font-size: 13px;">{{ $claim->proof_description }}</div>
                        <div style="margin-top: 10px;">
                            <a href="{{ route('claims.proof', $claim) }}" target="_blank" class="btn btn-outline btn-sm">
                                Open Private Proof File &rarr;
                            </a>
                        </div>
                    </div>

                    <div class="callout-info" style="border-radius: 8px; padding: 12px;">
                        <div class="caption" style="font-weight: 600; color: var(--primary-navy); margin-bottom: 4px;">Finder's Hidden Detail &amp; Custody</div>
                        <div style="font-size: 13px; color: var(--text-dark);">
                            {{ $claim->item->hidden_detail ?: 'No hidden distinguishing notes provided.' }}
                        </div>
                        <div class="caption" style="color: var(--primary-navy); margin-top: 8px;">
                            Custody location: <strong>{{ $claim->item->custody_location }}</strong>
                        </div>
                    </div>
                </div>

                <form action="{{ route('claims.verify', $claim) }}" method="POST" data-verify-form>
                    @csrf
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="notes-{{ $claim->id }}">Verification Notes (Required if rejecting claim)</label>
                        <input id="notes-{{ $claim->id }}" type="text" name="verification_notes" placeholder="e.g. Serial number verified / Proof photo unclear">
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm">
                            Approve &amp; Issue Pickup Code
                        </button>
                        <button type="submit" name="decision" value="reject" class="btn btn-danger btn-sm">
                            Reject Claim
                        </button>
                    </div>
                </form>
            </div>
        @empty
            <p class="caption" style="margin: 0; font-size: 13px;">No pending claims in the queue right now.</p>
        @endforelse
    </div>

    {{-- 2. Approved Claims Ready for Physical Handover --}}
    <div id="section-ready" class="card-surface" style="margin-bottom: 22px;">
        <h2 class="section-title" style="color: var(--emerald-found); margin-bottom: 16px;">
            2. Ready for Physical Handover ({{ $readyForHandover->count() }})
        </h2>

        @forelse($readyForHandover as $claim)
            <div class="handover-card">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 14px;">
                    <div>
                        <strong style="font-size: 16px; color: var(--text-dark);">{{ $claim->item->title }}</strong>
                        <div class="caption" style="margin-top: 3px;">
                            Verified Owner: <strong>{{ $claim->user->name }}</strong> ({{ $claim->user->email }})
                            &bull; Custody Point: <strong>{{ $claim->item->custody_location }}</strong>
                        </div>
                    </div>
                    <span class="badge badge-found-open" style="font-size: 12px; padding: 4px 12px;">
                        Pickup Code: {{ $claim->pickup_code }}
                    </span>
                </div>

                <form action="{{ route('claims.handover', $claim) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px;">
                        <div class="form-group">
                            <label for="pickup-{{ $claim->id }}">Verify Pickup Code</label>
                            <input id="pickup-{{ $claim->id }}" type="text" name="pickup_code" data-uppercase-trim placeholder="Enter claimant pickup code" required>
                        </div>
                        <div class="form-group">
                            <label for="recipient-{{ $claim->id }}">Recipient Name (Matching Campus ID)</label>
                            <input id="recipient-{{ $claim->id }}" type="text" name="recipient_name" value="{{ $claim->user->name }}" required>
                        </div>
                        <div class="form-group">
                            <label for="photo-{{ $claim->id }}">Handover Photo (Max 4MB)</label>
                            <div class="file-dropzone" data-file-dropzone style="padding: 8px;">
                                <input id="photo-{{ $claim->id }}" type="file" name="handover_photo" accept="image/*" data-max-kb="4096" required>
                                <div class="file-meta-row" data-file-meta>
                                    <span data-file-name></span>
                                    <button type="button" class="btn btn-outline btn-sm" data-file-clear>Remove</button>
                                </div>
                                <div data-file-preview style="display: none; margin-top: 8px;">
                                    <img data-file-img src="#" alt="Handover photo preview" style="max-height: 120px; border-radius: 8px; border: 1px solid var(--border-color);">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="handover-notes-{{ $claim->id }}">Handover Notes (Optional)</label>
                        <input id="handover-notes-{{ $claim->id }}" type="text" name="notes" placeholder="e.g. UPH Student ID verified in person.">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Complete Handover &amp; Print BAST</button>
                </form>
            </div>
        @empty
            <p class="caption" style="margin: 0; font-size: 13px;">No approved claims awaiting pickup.</p>
        @endforelse
    </div>

    {{-- 3. Recent Claims & BAST History --}}
    <div id="section-history" class="card-surface" data-table-filter-group>
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px;">
            <h2 class="section-title" style="color: var(--primary-navy); margin: 0;">3. Recent Claims &amp; BAST History</h2>
            @if($recentClaims->isNotEmpty())
                <div class="table-filter-bar">
                    <input type="search" data-table-search placeholder="Filter item or claimant..." style="max-width: 220px; min-height: 34px; font-size: 13px;">
                    <div class="filter-pills">
                        <button type="button" class="filter-pill is-active" data-table-status="all">All</button>
                        <button type="button" class="filter-pill" data-table-status="approved">Approved</button>
                        <button type="button" class="filter-pill" data-table-status="rejected">Rejected</button>
                    </div>
                </div>
            @endif
        </div>
        @if($recentClaims->isEmpty())
            <p class="caption" style="margin: 0; font-size: 13px;">No completed or closed claims yet.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Claimant</th>
                            <th>Status</th>
                            <th>Notes / BAST Slip</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentClaims as $claim)
                            <tr data-table-row data-row-status="{{ strtolower($claim->status) }}">
                                <td><a href="{{ route('items.show', $claim->item) }}" style="font-weight: 600; color: var(--primary-navy); text-decoration: none;">{{ $claim->item->title }}</a></td>
                                <td>{{ $claim->user->name }}</td>
                                <td><span class="badge badge-claimed">{{ strtoupper($claim->status) }}</span></td>
                                <td>
                                    @if($claim->handover_at)
                                        Handed over {{ $claim->handover_at->format('d M Y H:i') }} &mdash;
                                        <a href="{{ route('claims.bast', $claim) }}" class="btn btn-outline btn-sm">Print BAST</a>
                                    @else
                                        {{ $claim->verification_notes ?? '-' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr data-table-empty hidden>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">No matching claim records.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@else
    {{-- Track Claim Status for Standard Users (FR-10, CLM-08, CLM-11) --}}
    <div style="margin-bottom: 22px;">
        <h1 class="page-title">My Claims</h1>
        <p style="margin: 4px 0 0; color: var(--text-muted);">
            Track verification updates and pickup codes for your claimed items.
        </p>
    </div>

    @php
        $pendingCount = $myClaims->where('status', 'pending')->count();
        $approvedWaiting = $myClaims->where('status', 'approved')->whereNull('handover_at');
        $completedCount = $myClaims->whereNotNull('handover_at')->count();
    @endphp

    <div class="kpi-grid">
        <div class="kpi-card">
            <span class="kpi-label">Total Claims Submitted</span>
            <span class="kpi-value">{{ $myClaims->count() }}</span>
        </div>
        <div class="kpi-card">
            <span class="kpi-label">Pending Verification</span>
            <span class="kpi-value" style="color: var(--primary-navy);">{{ $pendingCount }}</span>
        </div>
        <div class="kpi-card">
            <span class="kpi-label">Ready for Pickup</span>
            <span class="kpi-value" style="color: var(--emerald-found);">{{ $approvedWaiting->count() }}</span>
        </div>
        <div class="kpi-card">
            <span class="kpi-label">Completed Handovers</span>
            <span class="kpi-value">{{ $completedCount }}</span>
        </div>
    </div>

    @foreach($approvedWaiting as $readyClaim)
        <div class="card-surface pickup-ticket-card" style="margin-bottom: 18px; padding: 0; overflow: hidden;">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: stretch;">
                <div style="padding: 20px 24px; flex: 1; min-width: 240px;">
                    <span class="badge badge-found-open" style="margin-bottom: 8px;">Ready for Pickup at Security</span>
                    <h2 class="section-title" style="margin-bottom: 6px;">{{ $readyClaim->item->title }}</h2>
                    <div class="caption" style="font-size: 13px; color: var(--text-dark);">
                        Custody location: <strong>{{ $readyClaim->item->custody_location }}</strong>
                        @if($readyClaim->decided_at)
                            &bull; Collect by: <strong>{{ $readyClaim->decided_at->addDays(7)->format('d M Y') }}</strong>
                        @endif
                    </div>
                </div>
                <div style="padding: 20px 26px; background: linear-gradient(135deg, #06246E 0%, #0A43B8 100%); color: #FFFFFF; display: flex; flex-direction: column; justify-content: center; align-items: center; border-left: 2px dashed rgba(255,255,255,0.28); min-width: 210px; gap: 8px;">
                    <div style="font-size: 10.5px; opacity: 0.82; text-transform: uppercase; letter-spacing: 0.8px;">One-Time Pickup Code</div>
                    <div style="font-size: 24px; font-weight: 800; letter-spacing: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $readyClaim->pickup_code }}</div>
                    <button type="button" class="btn btn-glass btn-sm" data-copy-text="{{ $readyClaim->pickup_code }}" style="color: #FFFFFF;">Copy Code</button>
                </div>
            </div>
        </div>
    @endforeach

    <div class="card-surface" data-table-filter-group>
        @if($myClaims->isEmpty())
            <div style="text-align: center; padding: 36px 16px;">
                <p style="margin: 0 0 12px; color: var(--text-muted);">You haven't submitted any ownership claims yet.</p>
                <a href="{{ route('dashboard', ['type' => 'found']) }}" class="btn btn-primary btn-sm">Browse Found Posts</a>
            </div>
        @else
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px;">
                <h2 class="section-title" style="margin: 0;">Claim History</h2>
                <div class="table-filter-bar">
                    <input type="search" data-table-search placeholder="Search item..." style="max-width: 200px; min-height: 34px; font-size: 13px;">
                    <div class="filter-pills">
                        <button type="button" class="filter-pill is-active" data-table-status="all">All</button>
                        <button type="button" class="filter-pill" data-table-status="pending">Pending</button>
                        <button type="button" class="filter-pill" data-table-status="approved">Approved</button>
                        <button type="button" class="filter-pill" data-table-status="rejected">Rejected</button>
                    </div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Found Item</th>
                            <th>Attempt</th>
                            <th>Status</th>
                            <th>Pickup Code / Verification Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myClaims as $claim)
                            <tr data-table-row data-row-status="{{ strtolower($claim->status) }}">
                                <td>
                                    <a href="{{ route('items.show', $claim->item) }}" style="font-weight: 600; color: var(--primary-navy); text-decoration: none;">
                                        {{ $claim->item->title }}
                                    </a>
                                    <div class="caption">Custody: {{ $claim->item->custody_location ?? '-' }}</div>
                                </td>
                                <td>#{{ $claim->attempt_no }}</td>
                                <td>
                                    @if($claim->status === 'approved')
                                        <span class="badge badge-found-open">APPROVED</span>
                                    @elseif($claim->status === 'rejected')
                                        <span class="badge badge-danger">REJECTED</span>
                                    @elseif($claim->status === 'pending')
                                        <span class="badge badge-claimed">PENDING</span>
                                    @else
                                        <span class="badge badge-expired">{{ strtoupper($claim->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($claim->status === 'approved' && !$claim->handover_at)
                                        <strong style="color: var(--emerald-found); font-size: 15px;">{{ $claim->pickup_code }}</strong>
                                        <div class="caption">Present this code and your UPH ID card within 7 days.</div>
                                    @elseif($claim->handover_at)
                                        <span style="color: var(--emerald-found); font-weight: 600;">
                                            Picked up on {{ $claim->handover_at->format('d M Y H:i') }}.
                                        </span>
                                    @elseif($claim->verification_notes)
                                        <span>{{ $claim->verification_notes }}</span>
                                    @else
                                        <span class="caption">Awaiting security review (target: 2 business days).</span>
                                    @endif
                                </td>
                                <td>
                                    @if($claim->status === 'pending')
                                        <form action="{{ route('claims.withdraw', $claim) }}"
                                              method="POST"
                                              data-confirm="Are you sure you want to withdraw this pending claim?"
                                              data-confirm-title="Withdraw Claim"
                                              data-confirm-label="Yes, Withdraw">
                                            @csrf
                                            <button type="submit" class="btn btn-outline btn-sm">Withdraw</button>
                                        </form>
                                    @else
                                        <span class="caption">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr data-table-empty hidden>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No matching claims found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
@endsection
