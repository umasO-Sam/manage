<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 仕入管理の検索・原価計算は経理資材担当と営業担当のみ閲覧できる。
 * データ入力・注文書・明細書・人工計算・レコード編集・担当者管理は
 * procurement.managerミドルウェア(経理資材担当限定)で別途保護する。
 */
class EnsurePurchasingViewer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canAccessPurchasing(),
            403,
            '仕入管理は経理資材担当・営業担当のみ利用できます。'
        );

        return $next($request);
    }
}
