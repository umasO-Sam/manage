<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 物件管理ボードを経理資材担当・役員・営業担当・資金管理者(とadministrator)に限定する。
 */
class EnsureProjectBoardUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canUseProjectBoard(),
            403,
            '物件管理ボードは経理資材担当・役員・営業担当・資金管理者のみ利用できます。'
        );

        return $next($request);
    }
}
