<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Menampilkan Konfigurasi Sistem, Domain Kampus, dan Kesehatan Sistem untuk IT Admin.
     * Catatan ROL-03: it_admin tidak pernah mengakses file bukti klaim maupun isi pesan terenkripsi.
     */
    public function systemHealth()
    {
        $dbHealthy = true;
        $dbError = null;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbHealthy = false;
            $dbError = $e->getMessage();
        }

        $health = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_connection' => config('database.default'),
            'db_healthy' => $dbHealthy,
            'db_error' => $dbError,
            'gd_loaded' => extension_loaded('gd'),
            'storage_link_exists' => is_link(public_path('storage')) || is_dir(public_path('storage')),
        ];

        $config = [
            'allowed_email_domains' => config('lostracer.allowed_email_domains'),
            'deposit_locations' => config('lostracer.deposit_locations'),
            'custody_locations' => config('lostracer.custody_locations'),
            'retention' => config('lostracer.retention'),
        ];

        $stats = [
            'total_users' => User::count(),
            'blocked_users' => User::where('is_blocked', true)->count(),
            'total_items' => Item::count(),
            'expired_items' => Item::where('status', 'expired')->count(),
        ];

        return view('admin.system', compact('health', 'config', 'stats'));
    }
}
