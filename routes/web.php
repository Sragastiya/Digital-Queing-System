<?php

use App\Http\Controllers\CustomerQueueController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\StaffQueueController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CustomerQueueController::class, 'index'])->name('home');
Route::post('/antrean', [CustomerQueueController::class, 'store'])->middleware('throttle:queue-write')->name('queue.join');
Route::get('/tiket/{token}', [CustomerQueueController::class, 'show'])->name('ticket.show');
Route::get('/tiket/{token}/status', [CustomerQueueController::class, 'status'])->middleware('throttle:ticket-status')->name('ticket.status');
Route::post('/tiket/{token}/batalkan', [CustomerQueueController::class, 'cancel'])->middleware('throttle:queue-write')->name('ticket.cancel');
Route::middleware('guest')->group(function () {
    Route::get('/staf/masuk', [StaffAuthController::class, 'create'])->name('login');
    Route::post('/staf/masuk', [StaffAuthController::class, 'store'])->middleware('throttle:staff-login')->name('login.store');
});
Route::middleware('auth')->prefix('staf')->name('staff.')->group(function () {
    Route::post('/keluar', [StaffAuthController::class, 'destroy'])->name('logout');
    Route::get('/', [StaffQueueController::class, 'index'])->name('index');
    Route::get('/status', [StaffQueueController::class, 'poll'])->name('poll');
    Route::get('/tambah', [StaffQueueController::class, 'create'])->name('create');
    Route::post('/tambah', [StaffQueueController::class, 'store'])->name('store');
    Route::post('/panggil', [StaffQueueController::class, 'call'])->name('call');
    Route::post('/pengaturan', [StaffQueueController::class, 'settings'])->name('settings');
    Route::post('/tiket/{ticket}/{action}', [StaffQueueController::class, 'transition'])->whereIn('action', ['arrive', 'complete', 'cancel'])->name('transition');
});
