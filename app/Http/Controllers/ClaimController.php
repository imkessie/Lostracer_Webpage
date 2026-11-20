<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Claim;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    /**
     * 1. Menampilkan Track Claim Status (user) atau Antrean Verifikasi & Serah-Terima (security).
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->isSecurity()) {
            $pendingClaims = Claim::with(['item.category', 'user'])
                ->where('status', 'pending')
                ->orderBy('created_at') // CLM-04: Urut sesuai waktu pengajuan
                ->get();

            $readyForHandover = Claim::with(['item.category', 'user', 'verifier'])
                ->where('status', 'approved')
                ->whereNull('handover_at')
                ->orderBy('decided_at')
                ->get();

            $recentClaims = Claim::with(['item', 'user', 'verifier'])
                ->where(function ($q) {
                    $q->whereIn('status', ['rejected', 'withdrawn', 'lapsed'])
                        ->orWhereNotNull('handover_at');
                })
                ->latest()
                ->limit(25)
                ->get();

            return view('claims.index', compact('pendingClaims', 'readyForHandover', 'recentClaims'));
        }

        // Tampilan Track Claim Status untuk peran user (FR-10)
        $myClaims = Claim::with(['item.category', 'verifier'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return view('claims.index', compact('myClaims'));
    }

    /**
     * 2. Mengajukan Klaim Barang Temuan dengan Bukti Kepemilikan (FR-06, AC-01, CLM-02, CLM-07).
     */
    public function store(Request $request, Item $item)
    {
        $user = Auth::user();

        if ($item->type !== 'found') {
            abort(404, 'Claims can only be submitted on found posts.');
        }

        if ($item->user_id === $user->id) {
            return back()->with('error', 'You cannot claim your own found post.');
        }

        if (! $item->isClaimable()) {
            return back()->with('error', 'This post is not accepting new claims because it already has an approved claim or is closed.');
        }

        $existingClaims = Claim::where('item_id', $item->id)
            ->where('user_id', $user->id)
            ->get();

        if ($existingClaims->whereIn('status', ['pending', 'approved'])->isNotEmpty()) {
            return back()->with('error', 'You already have an active claim on this item.');
        }

        $rejectedCount = $existingClaims->where('status', 'rejected')->count();
        $maxAttempts = (int) config('lostracer.retention.max_claim_attempts', 2);

        if ($rejectedCount >= $maxAttempts) {
            return back()->with('error', 'Maximum claim attempts (2 per item) reached. Please verify in person at the security post.');
        }

        $validated = $request->validate([
            'proof_description' => ['required', 'string', 'min:10', 'max:2000'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ], [
            'proof_description.required' => 'Proof description is required.',
            'proof_file.required' => 'At least one proof file is required.',
        ]);

        // PRV-05: Store proof file on the private 'local' disk
        $proofFilePath = $request->file('proof_file')->store('claims', 'local');

        DB::transaction(function () use ($item, $user, $validated, $proofFilePath, $rejectedCount) {
            Claim::create([
                'item_id' => $item->id,
                'user_id' => $user->id,
                'proof_description' => $validated['proof_description'],
                'proof_file_path' => $proofFilePath,
                'status' => 'pending',
                'attempt_no' => $rejectedCount + 1,
            ]);

            $item->update(['status' => 'claimed']);
        });

        return redirect()->route('claims.index')
            ->with('success', 'Claim submitted successfully and is awaiting security verification.');
    }

    /**
     * 3. Withdraw Pending Claim (CLM-11, FR-12, EDG-11).
     */
    public function withdraw(Claim $claim)
    {
        if ($claim->user_id !== Auth::id()) {
            abort(403, 'You may only withdraw your own claim.');
        }

        if ($claim->status !== 'pending') {
            return back()->with('error', 'Only pending claims can be withdrawn.');
        }

        DB::transaction(function () use ($claim) {
            $claim->update(['status' => 'withdrawn']);

            $item = $claim->item()->lockForUpdate()->first();
            if ($item && ! $item->isTerminal()) {
                $hasOtherActiveClaims = $item->claims()
                    ->whereIn('status', ['pending', 'approved'])
                    ->exists();

                if (! $hasOtherActiveClaims) {
                    $item->update(['status' => 'open']);
                }
            }
        });

        return redirect()->route('claims.index')
            ->with('success', 'Your claim has been withdrawn.');
    }

    /**
     * 4. Serve Private Proof File — Security Only (FR-07, AC-05, PRV-05, ROL-03).
     */
    public function proof(Claim $claim)
    {
        if (! Storage::disk('local')->exists($claim->proof_file_path)) {
            abort(404, 'Proof file not found.');
        }

        return Storage::disk('local')->response($claim->proof_file_path);
    }

    /**
     * 5. Verify Claim (Approve / Reject) by Security (CLM-05, CLM-08, STA-05, ROL-02).
     */
    public function verify(Request $request, Claim $claim)
    {
        if ($claim->status !== 'pending') {
            return back()->with('error', 'This claim has already been processed.');
        }

        $item = $claim->item;
        if ($item->isTerminal()) {
            return back()->with('error', 'This item is already in a terminal status.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'verification_notes' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ], [
            'verification_notes.required_if' => 'A rejection reason is required so the claimant can review it.',
        ]);

        if ($validated['decision'] === 'approve') {
            if ($item->hasApprovedClaim()) {
                return back()->with('error', 'This item already has an approved claim awaiting pickup.');
            }

            $pickupCode = $this->generateUniquePickupCode();

            DB::transaction(function () use ($claim, $item, $pickupCode, $validated) {
                $claim->update([
                    'status' => 'approved',
                    'verified_by' => Auth::id(),
                    'verification_notes' => $validated['verification_notes'] ?? 'Proof of ownership verified against physical item.',
                    'decided_at' => now(),
                    'pickup_code' => $pickupCode,
                ]);

                // STA-05: Keep item status as claimed while awaiting physical pickup
                $item->update(['status' => 'claimed']);

                AuditLog::record(
                    'CLAIM_APPROVED',
                    $item->id,
                    $claim->id,
                    sprintf(
                        'Claim #%d by %s approved. Pickup code issued (%s). %s',
                        $claim->id,
                        $claim->user->name,
                        $pickupCode,
                        $validated['verification_notes'] ?? ''
                    )
                );
            });

            return back()->with('success', 'Claim approved! Pickup code '.$pickupCode.' has been issued.');
        }

        // Reject claim (CLM-05)
        DB::transaction(function () use ($claim, $item, $validated) {
            $claim->update([
                'status' => 'rejected',
                'verified_by' => Auth::id(),
                'verification_notes' => $validated['verification_notes'],
                'decided_at' => now(),
            ]);

            $hasRemainingActive = $item->claims()
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if (! $hasRemainingActive && ! $item->isTerminal()) {
                $item->update(['status' => 'open']);
            }

            AuditLog::record(
                'CLAIM_REJECTED',
                $item->id,
                $claim->id,
                sprintf(
                    'Claim #%d by %s rejected. Reason: %s',
                    $claim->id,
                    $claim->user->name,
                    $validated['verification_notes']
                )
            );
        });

        return back()->with('success', 'Claim rejected with verification notes.');
    }

    /**
     * 6. Process Physical Handover & Handover Photo (FR-08, AC-03, HND-02, HND-03, HND-04).
     */
    public function handover(Request $request, Claim $claim)
    {
        if ($claim->status !== 'approved' || $claim->handover_at !== null) {
            return back()->with('error', 'Handover is only allowed for approved claims awaiting pickup.');
        }

        $item = $claim->item;
        if ($item->isTerminal()) {
            return back()->with('error', 'This item has already been returned or expired.');
        }

        $validated = $request->validate([
            'pickup_code' => ['required', 'string'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'handover_photo' => ['required', 'image', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'pickup_code.required' => 'Pickup code is required to verify handover.',
            'recipient_name.required' => 'Recipient name matching campus ID is required.',
            'handover_photo.required' => 'Handover photo documentation is required.',
        ]);

        // AC-03 & HND-02: Validate single-use pickup code
        if (strtoupper(trim($validated['pickup_code'])) !== $claim->pickup_code) {
            return back()
                ->withErrors(['pickup_code' => 'Invalid pickup code. Handover rejected.'])
                ->withInput();
        }

        // PRV-05: Store handover photo on private 'local' disk
        $handoverPhotoPath = $request->file('handover_photo')->store('handovers', 'local');

        DB::transaction(function () use ($claim, $item, $validated, $handoverPhotoPath) {
            $now = now();

            $claim->update(['handover_at' => $now]);

            // HND-04: Transition item to returned and reject remaining pending claims
            $item->update(['status' => 'returned']);

            $item->claims()
                ->where('id', '!=', $claim->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'verified_by' => Auth::id(),
                    'verification_notes' => 'Item has been handed over to the verified owner.',
                    'decided_at' => $now,
                ]);

            $auditNote = sprintf(
                'Handover completed. Officer: %s | Recipient: %s | Code: %s | Location: %s%s',
                Auth::user()->name,
                $validated['recipient_name'],
                $claim->pickup_code,
                $item->custody_location ?? '-',
                ! empty($validated['notes']) ? ' | Notes: '.$validated['notes'] : ''
            );

            AuditLog::record(
                'HANDOVER_COMPLETED',
                $item->id,
                $claim->id,
                $auditNote,
                $handoverPhotoPath
            );
        });

        return redirect()->route('claims.bast', $claim)
            ->with('success', 'Handover recorded successfully! You can now print the BAST slip.');
    }

    /**
     * 7. Menampilkan Halaman Cetak Berita Acara Serah Terima (BAST) (HND-05).
     */
    public function bast(Claim $claim)
    {
        if ($claim->status !== 'approved' || $claim->handover_at === null) {
            abort(404, 'BAST hanya tersedia setelah proses serah-terima selesai.');
        }

        $claim->load(['item.category', 'user', 'verifier']);

        $handoverLog = AuditLog::with('user')
            ->where('claim_id', $claim->id)
            ->where('action', 'HANDOVER_COMPLETED')
            ->latest()
            ->first();

        return view('claims.bast', compact('claim', 'handoverLog'));
    }

    /**
     * 8. Menyajikan Foto Handover dari Disk Privat untuk Petugas Security (PRV-05, NFR-06).
     */
    public function handoverPhoto(AuditLog $auditLog)
    {
        if (! $auditLog->photo_path || ! Storage::disk('local')->exists($auditLog->photo_path)) {
            abort(404, 'Foto dokumentasi serah-terima tidak ditemukan.');
        }

        return Storage::disk('local')->response($auditLog->photo_path);
    }

    private function generateUniquePickupCode(): string
    {
        do {
            $code = 'UPH-'.strtoupper(Str::random(6));
        } while (Claim::where('pickup_code', $code)->exists());

        return $code;
    }
}
