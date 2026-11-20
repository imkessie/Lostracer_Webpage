<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SecurityController;
use Illuminate\Support\Facades\Route;

// Rute untuk tamu (belum login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Rute logout
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rute terautentikasi & akun tidak diblokir
Route::middleware(['auth', 'active_user'])->group(function () {
    Route::redirect('/', '/dashboard');

    // Dual Feed & Pencarian Hashtag (semua peran: user, security, it_admin)
    Route::get('/dashboard', [ItemController::class, 'index'])->name('dashboard');

    // Pelaporan Barang (hanya user & security sesuai Role Access Matrix)
    Route::middleware('role:user,security')->group(function () {
        Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('/items', [ItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
        Route::post('/items/{item}/close', [ItemController::class, 'close'])->name('items.close');

        // Encrypted In-App Messaging (user & security; it_admin forbidden per ROL-03)
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/thread', [MessageController::class, 'thread'])->name('messages.thread');
        Route::post('/items/{item}/messages', [MessageController::class, 'store'])->name('items.messages.store');

        // Track Claim Status (user) & Antrean Klaim (security)
        Route::get('/claims', [ClaimController::class, 'index'])->name('claims.index');
    });

    // Pengajuan & Penarikan Klaim (khusus user)
    Route::middleware('role:user')->group(function () {
        Route::post('/items/{item}/claim', [ClaimController::class, 'store'])->name('items.claim');
        Route::post('/claims/{claim}/withdraw', [ClaimController::class, 'withdraw'])->name('claims.withdraw');
    });

    // Detail Barang & Foto Terproteksi (semua peran login, privasi diatur di controller)
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{item}/photo', [ItemController::class, 'photo'])->name('items.photo');

    // Modul Khusus Petugas Keamanan (security)
    Route::middleware('role:security')->group(function () {
        Route::get('/items/{item}/location', [ItemController::class, 'locationForm'])->name('items.location');
        Route::post('/items/{item}/location', [ItemController::class, 'updateLocation'])->name('items.location.update');

        Route::get('/claims/{claim}/proof', [ClaimController::class, 'proof'])->name('claims.proof');
        Route::post('/claims/{claim}/verify', [ClaimController::class, 'verify'])->name('claims.verify');
        Route::post('/claims/{claim}/handover', [ClaimController::class, 'handover'])->name('claims.handover');
        Route::get('/claims/{claim}/bast', [ClaimController::class, 'bast'])->name('claims.bast');

        Route::post('/security/items/{item}/delete', [SecurityController::class, 'deleteItem'])->name('security.items.delete');
        Route::post('/security/items/{item}/dispose', [SecurityController::class, 'disposeItem'])->name('security.items.dispose');

        Route::get('/security/users', [SecurityController::class, 'users'])->name('security.users');
        Route::post('/security/users/{user}/block', [SecurityController::class, 'blockUser'])->name('security.users.block');
        Route::post('/security/users/{user}/unblock', [SecurityController::class, 'unblockUser'])->name('security.users.unblock');

        Route::get('/audit-logs', [SecurityController::class, 'auditLogs'])->name('audit.index');
        Route::get('/audit-logs/export', [SecurityController::class, 'exportAuditLogs'])->name('audit.export');
        Route::get('/audit-logs/{auditLog}/photo', [ClaimController::class, 'handoverPhoto'])->name('audit.photo');
    });

    // Modul Khusus Admin IT (it_admin)
    Route::middleware('role:it_admin')->group(function () {
        Route::get('/admin/system', [AdminController::class, 'systemHealth'])->name('admin.system');
    });
});
