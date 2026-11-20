<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunDailyMaintenance extends Command
{
    /**
     * Nama dan signature perintah Artisan harian (FR-13).
     */
    protected $signature = 'lostracer:daily-maintenance';

    protected $description = 'Menjalankan pemeliharaan harian: cek Extended Shelf bulan ke-3, klaim lapsed >7 hari, dan kedaluwarsa barang bulan ke-6.';

    public function handle(): int
    {
        $pickupWindowDays = (int) config('lostracer.retention.pickup_window_days', 7);
        $systemActorId = User::where('role', 'security')->value('id')
            ?? User::where('role', 'it_admin')->value('id');

        // 1. Cek barang temuan yang melewati 3 bulan dan perlu pindah ke Extended Shelf (CUS-08)
        $dueExtendedCount = Item::where('type', 'found')
            ->whereIn('status', ['open', 'claimed'])
            ->get()
            ->filter(fn (Item $item) => $item->isDueForExtendedShelf())
            ->count();

        // 2. Tandai klaim approved yang tidak diambil > 7 hari menjadi lapsed (CLM-09)
        $lapsedCount = 0;
        $expiredClaims = Claim::with('item')
            ->where('status', 'approved')
            ->whereNull('handover_at')
            ->whereNotNull('decided_at')
            ->where('decided_at', '<=', now()->subDays($pickupWindowDays))
            ->get();

        foreach ($expiredClaims as $claim) {
            DB::transaction(function () use ($claim, &$lapsedCount) {
                $claim->update(['status' => 'lapsed']);
                $lapsedCount++;

                $item = $claim->item;
                if ($item && ! $item->isTerminal()) {
                    $hasOtherActive = $item->claims()
                        ->whereIn('status', ['pending', 'approved'])
                        ->exists();

                    if (! $hasOtherActive) {
                        $item->update(['status' => 'open']);
                    }
                }
            });
        }

        // 3. Tandai barang yang melewati masa simpan 6 bulan menjadi expired (CUS-05, EDG-09)
        $expiredItemsCount = 0;
        $overdueItems = Item::whereIn('status', ['open', 'claimed'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdueItems as $item) {
            DB::transaction(function () use ($item, $systemActorId, &$expiredItemsCount) {
                $item->update(['status' => 'expired']);

                $item->claims()
                    ->where('status', 'approved')
                    ->whereNull('handover_at')
                    ->update(['status' => 'lapsed']);

                $item->claims()
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'rejected',
                        'verification_notes' => 'Masa simpan laporan (6 bulan) telah berakhir.',
                        'decided_at' => now(),
                    ]);

                AuditLog::record(
                    'ITEM_EXPIRED',
                    $item->id,
                    null,
                    sprintf('Laporan "%s" kedaluwarsa otomatis setelah melewati masa simpan 6 bulan.', $item->title),
                    null,
                    $systemActorId ?? $item->user_id
                );

                $expiredItemsCount++;
            });
        }

        $this->info(sprintf(
            'Pemeliharaan selesai: %d barang perlu pindah Extended Shelf, %d klaim lapsed, %d barang expired.',
            $dueExtendedCount,
            $lapsedCount,
            $expiredItemsCount
        ));

        return self::SUCCESS;
    }
}
