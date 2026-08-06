<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 作業日報の確認を、経理資材担当のうち日報管理者フラグを付けた人(とadministrator)に限定する。
 */
class EnsureDailyReportReviewer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canReviewDailyReports(),
            403,
            'この画面は日報管理者のみ利用できます。'
        );

        return $next($request);
    }
}
