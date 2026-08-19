<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 参照ユーザ(role=viewer)が開ける画面を許可制で絞る。
 *
 * 参照ユーザに見せるのは「購入手配ボードの参照」と「勤務状況一覧」だけで、
 * 見せない画面のほうが圧倒的に多い。個々のルートに拒否を足していく方式だと
 * 画面が増えるたびに付け忘れが起きるため、ここに列挙したもの以外は403にする。
 *
 * ボードの絞り込み(購入手配だけ)はCardPolicyとCardControllerで見る。
 */
class RestrictReferenceViewer
{
    /** 参照ユーザが開けるルート名。ここに無いものはすべて403。 */
    private const ALLOWED_ROUTES = [
        // 購入手配ボードの参照(どのボードかは別途 Staff::canViewBoard() で見る)
        'cards.index',
        'cards.show',
        'attachments.download',
        'attachments.preview',
        // 勤務状況一覧
        'work-status.index',
        // 自分のアカウント周り
        'profile.edit',
        'profile.update',
        'password.confirm',
        'password.update',
        'password.force.edit',
        'password.force.update',
        'login',
        'logout',
        // 開発環境専用の権限切替(本番ではルート自体が存在しない)。
        // 参照ユーザに切り替えた後、元のロールに戻せるように許可する。
        'dev.role-switch.edit',
        'dev.role-switch.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isReferenceViewer()) {
            return $next($request);
        }

        abort_unless(
            in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true),
            403,
            '参照ユーザが使えるのは購入手配ボードの参照と勤務状況一覧の閲覧だけです。'
        );

        return $next($request);
    }
}
