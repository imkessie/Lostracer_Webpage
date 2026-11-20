<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SecurityController extends Controller
{
    /**
     * 1. Menghapus Postingan Laporan yang Melanggar Aturan Platform (FR-09, MOD-01, MOD-02).
     */
    public function deleteItem(Request $request, Item $item)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'A written reason is required when deleting a post.',
        ]);

        DB::transaction(function () use ($item, $validated) {
            $summary = sprintf(
                'Post #%d ("%s", type: %s, reporter: %s) deleted by security. Reason: %s',
                $item->id,
                $item->title,
                $item->type,
                $item->user->email,
                $validated['reason']
            );

            AuditLog::record('POST_DELETED', $item->id, null, $summary);

            if ($item->photo_path) {
                $disk = $item->is_sensitive ? 'local' : 'public';
                if (Storage::disk($disk)->exists($item->photo_path)) {
                    Storage::disk($disk)->delete($item->photo_path);
                }
            }

            $item->delete();
        });

        return redirect()->route('dashboard')
            ->with('success', 'Violating post deleted and recorded in the audit log.');
    }

    /**
     * 2. Record Disposal / Donation of Expired Item (CUS-05, EDG-09).
     */
    public function disposeItem(Request $request, Item $item)
    {
        if ($item->status !== 'expired') {
            return back()->with('error', 'Only expired items (past the 6-month retention period) can be marked as disposed.');
        }

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'notes.required' => 'Disposal or donation notes are required.',
        ]);

        DB::transaction(function () use ($item, $validated) {
            $item->update(['is_hidden' => true]);

            AuditLog::record(
                'ITEM_DISPOSED',
                $item->id,
                null,
                sprintf('Item "%s" disposed/donated after 6-month retention. Notes: %s', $item->title, $validated['notes'])
            );
        });

        return redirect()->route('audit.index')
            ->with('success', 'Item disposal has been recorded in the audit log.');
    }

    /**
     * 3. List User Accounts for Moderation (MOD-01..MOD-04).
     */
    public function users(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $statusFilter = in_array($request->get('status'), ['active', 'blocked'], true)
            ? $request->get('status')
            : 'all';

        $query = User::where('role', 'user')
            ->withCount(['items', 'claims'])
            ->orderByDesc('is_blocked')
            ->orderBy('name');

        if ($statusFilter === 'active') {
            $query->where('is_blocked', false);
        } elseif ($statusFilter === 'blocked') {
            $query->where('is_blocked', true);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
            });
        }

        $users = $query->get();
        $totalUsers = User::where('role', 'user')->count();
        $activeCount = User::where('role', 'user')->where('is_blocked', false)->count();
        $blockedCount = User::where('role', 'user')->where('is_blocked', true)->count();

        return view('security.users', compact('users', 'q', 'statusFilter', 'totalUsers', 'activeCount', 'blockedCount'));
    }

    /**
     * 4. Block Spam / Violating Account (MOD-01, MOD-03, EDG-10, ROL-02).
     */
    public function blockUser(Request $request, User $user)
    {
        if (! $user->isUser()) {
            return back()->with('error', 'Only standard user accounts can be blocked from moderation.');
        }

        if ($user->is_blocked) {
            return back()->with('error', 'This account is already blocked.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'A written reason is required to block an account.',
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update(['is_blocked' => true]);

            // MOD-03 & EDG-10: Hide open posts from public feed
            Item::where('user_id', $user->id)
                ->where('status', 'open')
                ->update(['is_hidden' => true]);

            // MOD-03: Automatically withdraw user's pending claims
            $pendingClaims = $user->claims()->where('status', 'pending')->get();
            foreach ($pendingClaims as $claim) {
                $claim->update(['status' => 'withdrawn']);

                $item = $claim->item;
                if ($item && ! $item->isTerminal()) {
                    $hasOtherActive = $item->claims()
                        ->whereIn('status', ['pending', 'approved'])
                        ->exists();
                    if (! $hasOtherActive) {
                        $item->update(['status' => 'open']);
                    }
                }
            }

            AuditLog::record(
                'USER_BLOCKED',
                null,
                null,
                sprintf('Account %s (%s) blocked. Reason: %s', $user->name, $user->email, $validated['reason'])
            );
        });

        return back()->with('success', 'Account '.$user->email.' has been blocked.');
    }

    /**
     * 5. Unblock User Account (MOD-04, ROL-02).
     */
    public function unblockUser(Request $request, User $user)
    {
        if (! $user->is_blocked) {
            return back()->with('error', 'This account is not currently blocked.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'A written reason is required to unblock an account.',
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->update(['is_blocked' => false]);

            // Restore open posts hidden during account suspension
            Item::where('user_id', $user->id)
                ->where('status', 'open')
                ->update(['is_hidden' => false]);

            AuditLog::record(
                'USER_UNBLOCKED',
                null,
                null,
                sprintf('Account %s (%s) unblocked. Reason: %s', $user->name, $user->email, $validated['reason'])
            );
        });

        return back()->with('success', 'Account '.$user->email.' has been unblocked.');
    }

    /**
     * 6. Menampilkan Log Audit Append-Only & Laporan Rekap (FR-08, HND-06).
     */
    public function auditLogs(Request $request)
    {
        $actionFilter = (string) $request->get('action', '');
        $q = trim((string) $request->get('q', ''));

        $query = AuditLog::with(['item', 'claim', 'user'])->latest();

        if ($actionFilter !== '' && in_array($actionFilter, config('lostracer.audit_actions'), true)) {
            $query->where('action', $actionFilter);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('notes', 'like', '%'.$q.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))
                    ->orWhereHas('item', fn ($i) => $i->where('title', 'like', '%'.$q.'%'));
            });
        }

        $logs = $query->paginate(30)->withQueryString();
        $actions = config('lostracer.audit_actions');

        // Ringkasan singkat rekap untuk petugas
        $summary = [
            'total_items' => Item::count(),
            'open_found' => Item::where('type', 'found')->whereIn('status', ['open', 'claimed'])->count(),
            'returned_items' => Item::where('status', 'returned')->count(),
            'expired_items' => Item::where('status', 'expired')->count(),
        ];

        return view('security.audit_logs', compact('logs', 'actions', 'actionFilter', 'q', 'summary'));
    }

    /**
     * 7. Ekspor Log Audit ke File CSV (Module 6: streamDownload).
     */
    public function exportAuditLogs()
    {
        $fileName = 'lostracer-audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Waktu (UTC)',
                'Aksi',
                'ID Barang',
                'Judul Barang',
                'ID Klaim',
                'Petugas / Aktor',
                'Email Aktor',
                'Catatan',
                'Ref Foto Handover',
            ]);

            AuditLog::with(['item', 'user'])
                ->orderBy('id')
                ->chunk(200, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            $log->created_at?->toIso8601String(),
                            $log->action,
                            $log->item_id,
                            $log->item?->title ?? '-',
                            $log->claim_id,
                            $log->user?->name ?? '-',
                            $log->user?->email ?? '-',
                            $log->notes,
                            $log->photo_path ?? '-',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
