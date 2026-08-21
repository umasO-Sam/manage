<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 作業日報一覧(提出・確認状況の一覧)の閲覧を、経理資材担当・上長・資金管理者・
 * administrator に加えて日報管理者に許す。
 *
 * 同じ supervisor.or.manager グループにある操作ログ・原価一覧は他人の賃金や原価に
 * 触れる画面なので、日報管理者には広げない。そのため専用の判定を分けている。
 */
class EnsureDailyReportListViewer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canViewDailyReportList(),
            403,
            'この画面は経理資材担当・上長・日報管理者のみ利用できます。'
        );

        return $next($request);
    }
}
