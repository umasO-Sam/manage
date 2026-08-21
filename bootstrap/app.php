<?php

use App\Http\Middleware\EnsureAttendanceManager;
use App\Http\Middleware\EnsureDailyReportListViewer;
use App\Http\Middleware\EnsureDailyReportReviewer;
use App\Http\Middleware\EnsureDailyReportReviewViewer;
use App\Http\Middleware\EnsureFundManager;
use App\Http\Middleware\EnsureProcurementManager;
use App\Http\Middleware\EnsureProjectBoardUser;
use App\Http\Middleware\EnsurePurchasingViewer;
use App\Http\Middleware\EnsureQuoteNumberUser;
use App\Http\Middleware\EnsureStaffManager;
use App\Http\Middleware\EnsureSupervisorOrManager;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\RestrictReferenceViewer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'attendance.manager' => EnsureAttendanceManager::class,
            'daily.report.reviewer' => EnsureDailyReportReviewer::class,
            'daily.report.list' => EnsureDailyReportListViewer::class,
            'daily.report.viewer' => EnsureDailyReportReviewViewer::class,
            'fund.manager' => EnsureFundManager::class,
            'procurement.manager' => EnsureProcurementManager::class,
            'project.board' => EnsureProjectBoardUser::class,
            'purchasing.viewer' => EnsurePurchasingViewer::class,
            'quote.number' => EnsureQuoteNumberUser::class,
            'staff.manager' => EnsureStaffManager::class,
            'supervisor.or.manager' => EnsureSupervisorOrManager::class,
        ]);
        $middleware->web(append: [
            // パスワードが変わったら、変更前から続いている他端末のセッションを
            // 次のリクエストで無効にする(ログイン中のスマホがそのまま使えてしまうのを防ぐ)。
            AuthenticateSession::class,
            RequirePasswordChange::class,
            // 参照ユーザは開ける画面を許可制で絞る(許可リストはミドルウェア側)。
            RestrictReferenceViewer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
