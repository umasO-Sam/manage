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
use App\Http\Controllers\DevRoleSwitchController;
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
use App\Http\Controllers\QuoteNumberController;
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
    Route::post('/boards/{workflow}/mark-others-read', [CardController::class, 'markOthersRead'])->name('cards.markOthersRead');
    Route::get('/boards/{workflow}/create', [CardController::class, 'create'])->name('cards.create');
    Route::post('/boards/{workflow}', [CardController::class, 'store'])->name('cards.store');

    // withTrashed: アーカイブ(論理削除)済みカードの詳細も履歴検索から参照できるようにする
    Route::get('/cards/{card}', [CardController::class, 'show'])->name('cards.show')->withTrashed();
    Route::post('/cards/{card}/move', [CardController::class, 'move'])->name('cards.move');
    Route::post('/cards/{card}/advance-to-input', [CardController::class, 'advanceToInput'])->name('cards.advanceToInput');
    Route::post('/cards/{card}/to-input', [CardController::class, 'toInput'])->name('cards.toInput');
    Route::post('/cards/{card}/revert', [CardController::class, 'revert'])->name('cards.revert');
    Route::post('/cards/{card}/archive-now', [CardController::class, 'archiveNow'])->name('cards.archiveNow');
    Route::delete('/cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
    Route::post('/cards/{card}/attachments', [CardController::class, 'storeAttachments'])->name('cards.attachments.store');
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
    // 勤怠管理者による代理申請。{leaveRequest} より前に置かないとIDとして食われる。
    Route::get('/leave-requests/proxy/create', [LeaveRequestController::class, 'createProxy'])
        ->middleware('attendance.manager')->name('leave-requests.proxy.create');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    // 承認待ち一覧は経理資材担当・上長限定。個別の承認/却下(decide)は承認者本人に
    // 指定された社員であればPolicyで許可する(メール内リンクから詳細経由で操作できる)。
    Route::get('/leave-requests/approvals', [LeaveRequestController::class, 'approvals'])
        ->middleware('supervisor.or.manager')->name('leave-requests.approvals');
    Route::post('/leave-requests/approvals/bulk-approve', [LeaveRequestController::class, 'bulkApprove'])
        ->middleware('supervisor.or.manager')->name('leave-requests.bulk-approve');
    // 勤怠管理者の反映確認一覧。{leaveRequest} より前に置かないとIDとして食われる。
    Route::get('/leave-requests/cancellations', [LeaveRequestController::class, 'cancellations'])
        ->middleware('attendance.manager')->name('leave-requests.cancellations');
    Route::put('/leave-requests/{leaveRequest}/attendance-decide', [LeaveRequestController::class, 'attendanceDecide'])
        ->middleware('attendance.manager')->name('leave-requests.attendance.decide');
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::put('/leave-requests/{leaveRequest}/decide', [LeaveRequestController::class, 'decide'])->name('leave-requests.decide');
    Route::post('/leave-requests/{leaveRequest}/cancel-request', [LeaveRequestController::class, 'requestCancel'])->name('leave-requests.cancel.request');
    Route::put('/leave-requests/{leaveRequest}/cancel-decide', [LeaveRequestController::class, 'decideCancel'])->name('leave-requests.cancel.decide');
    Route::put('/leave-requests/{leaveRequest}/cancel-reflect', [LeaveRequestController::class, 'reflectCancel'])->name('leave-requests.cancel.reflect');
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

    // 開発環境専用の権限切替(テスト用)。本番ではルート自体を登録しない。
    if (! app()->environment('production')) {
        Route::get('/dev/role-switch', [DevRoleSwitchController::class, 'edit'])->name('dev.role-switch.edit');
        Route::put('/dev/role-switch', [DevRoleSwitchController::class, 'update'])->name('dev.role-switch.update');
    }

    // 見積番号の採番: 営業担当・上長・役員・経理資材担当・資金管理者。
    Route::middleware('quote.number')->group(function () {
        Route::get('/quote-numbers', [QuoteNumberController::class, 'index'])->name('quote-numbers.index');
        Route::post('/quote-numbers', [QuoteNumberController::class, 'store'])->name('quote-numbers.store');
        Route::get('/quote-numbers/lookup', [QuoteNumberController::class, 'lookup'])->name('quote-numbers.lookup');
        Route::get('/quote-numbers/search', [QuoteNumberController::class, 'search'])->name('quote-numbers.search');
        // 取得ログはadministrator専用(コントローラ側で判定)。
        Route::get('/quote-numbers/logs', [QuoteNumberController::class, 'logs'])->name('quote-numbers.logs');
        Route::put('/quote-numbers/{quoteNumber}', [QuoteNumberController::class, 'update'])->name('quote-numbers.update');
        Route::delete('/quote-numbers/{quoteNumber}', [QuoteNumberController::class, 'destroy'])->name('quote-numbers.destroy');
    });

    // 物件管理ボード: 経理資材担当・役員・営業担当・資金管理者。
    Route::middleware('project.board')->group(function () {
        Route::get('/projects', [ProjectBoardController::class, 'index'])->name('projects.index');
        Route::get('/projects/history', [ProjectBoardController::class, 'history'])->name('projects.history');
        Route::get('/projects/create', [ProjectBoardController::class, 'create'])->name('projects.create');
        Route::get('/projects/orders/search', [ProjectBoardController::class, 'searchOrders'])->name('projects.orders.search');
        Route::post('/projects', [ProjectBoardController::class, 'store'])->name('projects.store');
        Route::get('/projects/{card}', [ProjectBoardController::class, 'show'])->name('projects.show')->withTrashed();
        Route::get('/projects/{card}/order', [ProjectBoardController::class, 'editOrder'])->name('projects.order.edit');
        Route::put('/projects/{card}/order', [ProjectBoardController::class, 'updateOrder'])->name('projects.order.update');
        Route::post('/projects/{card}/attachments', [ProjectBoardController::class, 'storeAttachment'])->name('projects.attachments.store');
        Route::post('/projects/{card}/advance', [ProjectBoardController::class, 'advance'])->name('projects.advance');
        Route::post('/projects/{card}/revert', [ProjectBoardController::class, 'revert'])->name('projects.revert');
        Route::delete('/projects/{card}/hide', [ProjectBoardController::class, 'hide'])->name('projects.hide');
        Route::delete('/projects/{card}', [ProjectBoardController::class, 'destroy'])->name('projects.destroy');
    });

    // 取引先一覧(銀行・取引区分・締め日・支払条件)は資金管理者限定。
    Route::middleware('fund.manager')->group(function () {
        Route::get('/business-partners', [BusinessPartnerController::class, 'index'])->name('business-partners.index');
        Route::put('/business-partners/bulk-update', [BusinessPartnerController::class, 'bulkUpdate'])->name('business-partners.bulk-update');
        Route::post('/business-partners/bulk-paste', [BusinessPartnerController::class, 'storeBulkPaste'])->name('business-partners.bulk-paste');
        Route::put('/business-partners/{businessPartner}', [BusinessPartnerController::class, 'update'])->name('business-partners.update');
        Route::post('/business-partners/{businessPartner}/confirm', [BusinessPartnerController::class, 'confirm'])->name('business-partners.confirm');
        Route::delete('/business-partners/{businessPartner}', [BusinessPartnerController::class, 'destroy'])->name('business-partners.destroy');
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

    // 作業日報確認は経理資材担当・上長・役員・資金管理者が閲覧でき、
    // 確認と差し戻しは日報管理者フラグを付けた人だけが行う。
    Route::get('/daily-reports/review', [DailyReportReviewController::class, 'index'])
        ->middleware('daily.report.viewer')->name('daily-reports.review.index');
    Route::post('/daily-reports/review/{dailyReport}/decide', [DailyReportReviewController::class, 'decide'])
        ->middleware('daily.report.reviewer')->name('daily-reports.review.decide');

    // データ入力・注文書・明細書・原価一覧・レコード編集は経理資材担当限定。
    Route::middleware('procurement.manager')->group(function () {
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
