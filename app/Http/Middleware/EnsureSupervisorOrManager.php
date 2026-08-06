<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 経理資材担当または上長のみが利用できる画面を保護する。
 * 作業日報確認・作業日報一覧・操作ログ・原価一覧・申請承認一覧など、
 * 他の社員の勤怠・原価情報をまとめて閲覧する画面が対象。
 */
class EnsureSupervisorOrManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isSupervisorOrManager(),
            403,
            'この画面は経理資材担当・上長のみ利用できます。'
        );

        return $next($request);
    }
}
