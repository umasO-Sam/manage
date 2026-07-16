<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/boards/purchase');

Route::middleware('auth')->group(function () {
    Route::get('/boards/{workflow}', [CardController::class, 'index'])->name('cards.index');
    Route::get('/boards/{workflow}/create', [CardController::class, 'create'])->name('cards.create');
    Route::post('/boards/{workflow}', [CardController::class, 'store'])->name('cards.store');

    Route::get('/cards/{card}', [CardController::class, 'show'])->name('cards.show');
    Route::post('/cards/{card}/move', [CardController::class, 'move'])->name('cards.move');
    Route::post('/cards/{card}/revert', [CardController::class, 'revert'])->name('cards.revert');
    Route::post('/cards/{card}/archive-now', [CardController::class, 'archiveNow'])->name('cards.archiveNow');
    Route::get('/attachments/{attachment}/download', [CardController::class, 'downloadAttachment'])->name('attachments.download');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('procurement.manager')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    });
});

require __DIR__.'/auth.php';
