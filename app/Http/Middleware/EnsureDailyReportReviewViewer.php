<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 作業日報確認の閲覧を、経理資材担当・上長・役員・資金管理者(とadministrator)に限定する。
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
            'この画面は経理資材担当・上長・役員・資金管理者のみ閲覧できます。'
        );

        return $next($request);
    }
}
