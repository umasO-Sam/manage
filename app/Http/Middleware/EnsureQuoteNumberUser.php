<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 見積番号の採番を、営業担当・上長・役員・経理資材担当・資金管理者に限定する。
 */
class EnsureQuoteNumberUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canAllocateQuoteNumber(),
            403,
            '見積番号の採番は営業担当・上長・役員・経理資材担当・資金管理者のみ利用できます。'
        );

        return $next($request);
    }
}
