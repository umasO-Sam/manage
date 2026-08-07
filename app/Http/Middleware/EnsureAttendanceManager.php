<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 承認済み申請の取消を反映してよいかの最終判断を、勤怠管理者(とadministrator)に限定する。
 */
class EnsureAttendanceManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canManageAttendance(),
            403,
            'この画面は勤怠管理者のみ利用できます。'
        );

        return $next($request);
    }
}
