<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * 勤務状況一覧(全社員の休暇・出勤状況を日付×社員のグリッドで確認する画面、
 * フェーズ3以降ロードマップ項目)。メニュー構成を先に用意するための準備中画面で、
 * 実装はまだ行っていない。
 */
class WorkStatusController extends Controller
{
    public function index(): View
    {
        return view('work-status.index');
    }
}
