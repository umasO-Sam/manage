<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 申請承認(休暇・休出の承認一覧)を、上長・勤怠管理者(とadministrator)に限定する。
 *
 * 画面に出るのは「自分が承認者になっている申請」だけなので、承認を任される上長と
 * 勤怠全体を見る勤怠管理者に絞る。経理資材担当はロールだけでは開けない。
 */
class EnsureLeaveApprovalViewer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canViewLeaveApprovals(),
            403,
            'この画面は上長・勤怠管理者のみ利用できます。'
        );

        return $next($request);
    }
}
