<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\CardCommentController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CostAnalysisController;
use App\Http\Controllers\LaborCostController;
use App\Http\Controllers\OrderNumberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseDetailController;
use App\Http\Controllers\PurchaseInputController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/boards/purchase');

Route::middleware('auth')->group(function () {
    Route::get('/boards/{workflow}', [CardController::class, 'index'])->name('cards.index');
    Route::get('/boards/{workflow}/create', [CardController::class, 'create'])->name('cards.create');
    Route::post('/boards/{workflow}', [CardController::class, 'store'])->name('cards.store');

    // withTrashed: アーカイブ(論理削除)済みカードの詳細も履歴検索から参照できるようにする
    Route::get('/cards/{card}', [CardController::class, 'show'])->name('cards.show')->withTrashed();
    Route::post('/cards/{card}/move', [CardController::class, 'move'])->name('cards.move');
    Route::post('/cards/{card}/revert', [CardController::class, 'revert'])->name('cards.revert');
    Route::post('/cards/{card}/archive-now', [CardController::class, 'archiveNow'])->name('cards.archiveNow');
    Route::delete('/cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
    Route::post('/cards/{card}/comments', [CardCommentController::class, 'store'])->name('cards.comments.store');
    Route::get('/attachments/{attachment}/download', [CardController::class, 'downloadAttachment'])->name('attachments.download');
    Route::get('/attachments/{attachment}/preview', [CardController::class, 'previewAttachment'])->name('attachments.preview');

    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // 仕入管理の検索・原価計算は資材管理担当者・営業担当が閲覧できる。
    Route::middleware('purchasing.viewer')->group(function () {
        Route::get('/purchasing', [PurchaseDetailController::class, 'index'])->name('purchasing.index');
        Route::get('/purchasing/cost', [CostAnalysisController::class, 'index'])->name('purchasing.cost.index');
    });

    // データ入力・注文書・明細書・人工計算・レコード編集・担当者管理は資材管理担当者限定。
    Route::middleware('procurement.manager')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

        Route::get('/order-numbers', [OrderNumberController::class, 'index'])->name('order-numbers.index');
        Route::get('/order-numbers/create', [OrderNumberController::class, 'create'])->name('order-numbers.create');
        Route::post('/order-numbers', [OrderNumberController::class, 'store'])->name('order-numbers.store');
        Route::delete('/order-numbers/{orderNumber}', [OrderNumberController::class, 'destroy'])->name('order-numbers.destroy');

        Route::get('/cards/{card}/edit', [CardController::class, 'edit'])->name('cards.edit');
        Route::put('/cards/{card}', [CardController::class, 'update'])->name('cards.update');

        Route::get('/purchasing/input', [PurchaseInputController::class, 'create'])->name('purchasing.input');
        Route::post('/purchasing/input', [PurchaseInputController::class, 'store'])->name('purchasing.input.store');

        Route::get('/purchasing/{purchaseDetail}/edit', [PurchaseDetailController::class, 'edit'])->name('purchasing.edit');
        Route::put('/purchasing/{purchaseDetail}', [PurchaseDetailController::class, 'update'])->name('purchasing.update');

        Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])->name('purchasing.orders.index');
        Route::post('/purchasing/orders/print', [PurchaseOrderController::class, 'print'])->name('purchasing.orders.print');

        Route::get('/purchasing/invoices', [PurchaseInvoiceController::class, 'index'])->name('purchasing.invoices.index');
        Route::post('/purchasing/invoices/print', [PurchaseInvoiceController::class, 'print'])->name('purchasing.invoices.print');

        Route::get('/purchasing/labor', [LaborCostController::class, 'index'])->name('purchasing.labor.index');
    });
});

require __DIR__.'/auth.php';
