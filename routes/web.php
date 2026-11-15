<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\MessageController;
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

// Rute yang membutuhkan login
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::view('/dashboard', 'dashboard');

    // Rute Lapor Barang
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');


    Route::resource('items', ItemController::class);


    
    Route::get('/dashboard', [ItemController::class, 'index'])->name('dashboard');

    Route::post('/items/{item}/claims', [ClaimController::class, 'store'])->name('claims.store');

    Route::get('/messages/{item}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{item}', [MessageController::class, 'store'])->name('messages.store');


    // 1. Central Inbox List Page
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');

    // 2. Specific Item Chat Conversation (already created)
    Route::get('/messages/{item}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{item}', [MessageController::class, 'store'])->name('messages.store');
});