<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 操作ログ一覧。ルート側のsupervisor.or.managerミドルウェアにより
 * 経理資材担当・上長のみがアクセスでき、常に全員分のログを表示する。
 *
 * 扱うのは勤怠(作業日報・休暇/休出申請)と人工レコードの操作だけ。物件カードの削除は
 * 物件管理の「物件履歴」に控えとして出るため、ここには出さない(2026-08-21)。
 */
class OperationLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = OperationLog::with(['staff', 'owner'])
            ->whereNotIn('action', OperationLog::PROJECT_ACTIONS)
            ->orderByDesc('created_at');

        return view('operation-logs.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'isPrivileged' => true,
        ]);
    }
}
