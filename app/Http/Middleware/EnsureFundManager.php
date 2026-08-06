<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 取引先一覧など、銀行・支払条件を扱う画面を資金管理者(とadministrator)に限定する。
 */
class EnsureFundManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canManageBusinessPartners(),
            403,
            'この画面は資金管理者のみ利用できます。'
        );

        return $next($request);
    }
}
