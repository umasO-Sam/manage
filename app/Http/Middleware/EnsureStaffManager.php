<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 担当者管理(ＩＤ管理)を保護する。経理資材担当に加え、役員・資金管理者も
 * 権限の付与・変更のためにこの画面を使うため、procurement.managerより広い。
 * 「誰にどのフラグを付けられるか」は StaffController 側で個別に落とす。
 */
class EnsureStaffManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canManageStaff(),
            403,
            '担当者管理は経理資材担当・役員・資金管理者のみ利用できます。'
        );

        return $next($request);
    }
}
