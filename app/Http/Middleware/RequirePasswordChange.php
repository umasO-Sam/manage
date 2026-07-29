<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * must_change_password=trueのアカウントは、パスワード変更ページとログアウト以外への
 * アクセスを強制的にリダイレクトする(次回ログイン時1回限りのパスワード変更強制)。
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->must_change_password
            && ! $request->routeIs('password.force.*')
            && ! $request->routeIs('logout')
        ) {
            return redirect()->route('password.force.edit');
        }

        return $next($request);
    }
}
