<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\CardCommentController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CostAnalysisController;
use App\Http\Controllers\CostReportController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DailyReportListController;
use App\Http\Controllers\DailyReportReviewController;
use App\Http\Controllers\EstimateAssistController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LaborCostController;
use App\Http\Controllers\LaborRecordController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\OperationLogController;
use App\Http\Controllers\OrderNumberController;
use App\Http\Controllers\PersonalCalendarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectBoardController;
use App\Http\Controllers\PurchaseDetailController;
use App\Http\Controllers\PurchaseInputController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\WorkStatusController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/boards/purchase');

Route::middleware('auth')->group(function () {
    Route::get('/boards/{workflow}', [CardController::class, 'index'])->name('cards.index');
    Route::get('/boards/{workflow}/create', [CardController::class, 'create'])->name('cards.create');
    Route::post('/boards/{workflow}', [CardController::class, 'store'])->name('cards.store');

    // withTrashed: アーカイブ(論理削除)済みカードの詳細も履歴検索から参照できるようにする
    Route::get('/cards/{card}', [CardController::class, 'show'])->name('cards.show')->withTrashed();
    Route::post('/cards/{card}/move', [CardController::class, 'move'])->name('cards.move');
    Route::post('/cards/{card}/advance-to-input', [CardController::class, 'advanceToInput'])->name('cards.advanceToInput');
    Route::post('/cards/{card}/revert', [CardController::class, 'revert'])->name('cards.revert');
    Route::post('/cards/{card}/archive-now', [CardController::class, 'archiveNow'])->name('cards.archiveNow');
    Route::delete('/cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
    Route::post('/cards/{card}/comments', [CardCommentController::class, 'store'])->name('cards.comments.store');
    Route::get('/attachments/{attachment}/download', [CardController::class, 'downloadAttachment'])->name('attachments.download');
    Route::get('/attachments/{attachment}/preview', [CardController::class, 'previewAttachment'])->name('attachments.preview');

    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');

    Route::get('/daily-reports', [DailyReportController::class, 'show'])->name('daily-reports.show');
    Route::post('/daily-reports', [DailyReportController::class, 'store'])->name('daily-reports.store');

    Route::get('/my-calendar', [PersonalCalendarController::class, 'show'])->name('my-calendar.show');

    Route::get('/work-status', [WorkStatusController::class, 'index'])->name('work-status.index');

    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::get('/leave-requests/create', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    // 承認待ち一覧は経理資材担当・上長限定。個別の承認/却下(decide)は承認者本人に
    // 指定された社員であればPolicyで許可する(メール内リンクから詳細経由で操作できる)。
    Route::get('/leave-requests/approvals', [LeaveRequestController::class, 'approvals'])
        ->middleware('supervisor.or.manager')->name('leave-requests.approvals');
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::put('/leave-requests/{leaveRequest}/decide', [LeaveRequestController::class, 'decide'])->name('leave-requests.decide');
    Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'withdraw'])->name('leave-requests.withdraw');

    // 他の社員の勤怠・原価情報をまとめて見る画面は経理資材担当・上長限定。
    // 作業日報確認(review)は人工データの確定を伴う経理資材担当の業務のため、
    // このグループではなくprocurement.managerグループに置く。
    Route::middleware('supervisor.or.manager')->group(function () {
        Route::get('/daily-reports/list', [DailyReportListController::class, 'index'])->name('daily-reports.list.index');
        Route::get('/operation-logs', [OperationLogController::class, 'index'])->name('operation-logs.index');
        Route::get('/purchasing/cost-report', [CostReportController::class, 'index'])->name('purchasing.cost-report.index');
        Route::get('/purchasing/cost-report/results', [CostReportController::class, 'results'])->name('purchasing.cost-report.results');
        Route::get('/purchasing/cost-report/export', [CostReportController::class, 'export'])->name('purchasing.cost-report.export');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // 仕入管理の検索・原価計算・人工計算は経理資材担当・営業担当が閲覧できる。
    Route::middleware('purchasing.viewer')->group(function () {
        Route::get('/purchasing', [PurchaseDetailController::class, 'index'])->name('purchasing.index');
        Route::get('/purchasing/cost', [CostAnalysisController::class, 'index'])->name('purchasing.cost.index');
        Route::get('/purchasing/cost/export', [CostAnalysisController::class, 'export'])->name('purchasing.cost.export');
        Route::get('/purchasing/labor', [LaborCostController::class, 'index'])->name('purchasing.labor.index');
        Route::get('/purchasing/estimate', [EstimateAssistController::class, 'index'])->name('purchasing.estimate.index');
    });

    // 物件管理ボード: 経理資材担当・役員・営業担当・資金管理者。
    Route::middleware('project.board')->group(function () {
        Route::get('/projects', [ProjectBoardController::class, 'index'])->name('projects.index');
        Route::get('/projects/create', [ProjectBoardController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectBoardController::class, 'store'])->name('projects.store');
        Route::get('/projects/{card}', [ProjectBoardController::class, 'show'])->name('projects.show')->withTrashed();
        Route::get('/projects/{card}/order', [ProjectBoardController::class, 'editOrder'])->name('projects.order.edit');
        Route::put('/projects/{card}/order', [ProjectBoardController::class, 'updateOrder'])->name('projects.order.update');
        Route::post('/projects/{card}/attachments', [ProjectBoardController::class, 'storeAttachment'])->name('projects.attachments.store');
        Route::post('/projects/{card}/advance', [ProjectBoardController::class, 'advance'])->name('projects.advance');
        Route::delete('/projects/{card}/hide', [ProjectBoardController::class, 'hide'])->name('projects.hide');
    });

    // 取引先一覧(銀行・取引区分・締め日・支払条件)は資金管理者限定。
    Route::middleware('fund.manager')->group(function () {
        Route::get('/business-partners', [BusinessPartnerController::class, 'index'])->name('business-partners.index');
        Route::put('/business-partners/{businessPartner}', [BusinessPartnerController::class, 'update'])->name('business-partners.update');
        Route::post('/business-partners/{businessPartner}/confirm', [BusinessPartnerController::class, 'confirm'])->name('business-partners.confirm');
    });

    // 担当者管理(ＩＤ管理)は経理資材担当・役員・資金管理者が使う。
    // 「誰にどのフラグを付けられるか」は StaffController 側で個別に制御する。
    Route::middleware('staff.manager')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::post('/staff/bulk-update', [StaffController::class, 'bulkUpdate'])->name('staff.bulk-update');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });

    // データ入力・注文書・明細書・原価一覧・レコード編集は経理資材担当限定。
    Route::middleware('procurement.manager')->group(function () {
        Route::get('/daily-reports/review', [DailyReportReviewController::class, 'index'])->name('daily-reports.review.index');
        Route::post('/daily-reports/review/{dailyReport}/decide', [DailyReportReviewController::class, 'decide'])->name('daily-reports.review.decide');

        Route::get('/order-numbers', [OrderNumberController::class, 'index'])->name('order-numbers.index');
        Route::get('/order-numbers/create', [OrderNumberController::class, 'create'])->name('order-numbers.create');
        Route::post('/order-numbers', [OrderNumberController::class, 'store'])->name('order-numbers.store');
        Route::put('/order-numbers/{orderNumber}', [OrderNumberController::class, 'update'])->name('order-numbers.update');
        Route::delete('/order-numbers/{orderNumber}', [OrderNumberController::class, 'destroy'])->name('order-numbers.destroy');

        Route::get('/labor-records', [LaborRecordController::class, 'index'])->name('labor-records.index');
        Route::put('/labor-records/{laborRecord}', [LaborRecordController::class, 'update'])->name('labor-records.update');
        Route::delete('/labor-records/{laborRecord}', [LaborRecordController::class, 'destroy'])->name('labor-records.destroy');

        Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::get('/holidays/calendar', [HolidayController::class, 'calendar'])->name('holidays.calendar');
        Route::get('/holidays/create', [HolidayController::class, 'create'])->name('holidays.create');
        Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::get('/holidays/{holiday}/edit', [HolidayController::class, 'edit'])->name('holidays.edit');
        Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::get('/cards/{card}/edit', [CardController::class, 'edit'])->name('cards.edit');
        Route::put('/cards/{card}', [CardController::class, 'update'])->name('cards.update');

        Route::get('/purchasing/input', [PurchaseInputController::class, 'create'])->name('purchasing.input');
        Route::post('/purchasing/input', [PurchaseInputController::class, 'store'])->name('purchasing.input.store');
        Route::post('/purchasing/input/bulk-paste', [PurchaseInputController::class, 'storeBulkPaste'])->name('purchasing.input.bulk-paste');
        Route::post('/purchasing/input/labor-bulk-paste', [PurchaseInputController::class, 'storeLaborBulkPaste'])->name('purchasing.input.labor-bulk-paste');

        Route::get('/purchasing/{purchaseDetail}/edit', [PurchaseDetailController::class, 'edit'])->name('purchasing.edit');
        Route::put('/purchasing/{purchaseDetail}', [PurchaseDetailController::class, 'update'])->name('purchasing.update');
        Route::delete('/purchasing/{purchaseDetail}', [PurchaseDetailController::class, 'destroy'])->name('purchasing.destroy');
        Route::post('/purchasing/bulk-update', [PurchaseDetailController::class, 'bulkUpdate'])->name('purchasing.bulk-update');
        Route::post('/purchasing/bulk-delete', [PurchaseDetailController::class, 'bulkDestroy'])->name('purchasing.bulk-delete');
        Route::post('/purchasing/labor/bulk-confirm', [LaborCostController::class, 'bulkConfirm'])->name('purchasing.labor.bulk-confirm');

        Route::get('/purchasing/orders', [PurchaseOrderController::class, 'index'])->name('purchasing.orders.index');
        Route::post('/purchasing/orders/print', [PurchaseOrderController::class, 'print'])->name('purchasing.orders.print');

        Route::get('/purchasing/invoices', [PurchaseInvoiceController::class, 'index'])->name('purchasing.invoices.index');
        Route::post('/purchasing/invoices/print', [PurchaseInvoiceController::class, 'print'])->name('purchasing.invoices.print');
    });
});

require __DIR__.'/auth.php';
