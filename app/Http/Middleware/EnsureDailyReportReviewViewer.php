<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 作業日報の2画面(作業日報確認・作業日報一覧)の閲覧を、日報管理者・上長・役員
 * (とadministrator)に限定する。経理資材担当・資金管理者はロールだけでは開けない。
 * 確認・差し戻しの操作は EnsureDailyReportReviewer で日報管理者だけに絞る。
 */
class EnsureDailyReportReviewViewer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canViewDailyReportReviews(),
            403,
            'この画面は日報管理者・上長・役員のみ閲覧できます。'
        );

        return $next($request);
    }
}
