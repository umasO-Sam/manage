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
        $action = (string) $request->query('action', '');
        $keyword = trim((string) $request->query('q', ''));

        // 選べる操作は、この画面に出る種類だけにする(物件管理側のものは並べない)。
        $actions = collect(OperationLog::ACTIONS)
            ->except(OperationLog::PROJECT_ACTIONS)
            ->all();

        $logs = OperationLog::with(['staff', 'owner'])
            ->whereNotIn('action', OperationLog::PROJECT_ACTIONS)
            ->when(array_key_exists($action, $actions), fn ($q) => $q->where('action', $action))
            // 備考には「休日勤務申請 2026/09/12（注番 A-1／本社／振休 2026/09/16）」のように
            // 対象日と申請内容が入っている。日付や注番をそのまま打って探せるようにする。
            ->when($keyword !== '', fn ($q) => $q->where('description', 'like', "%{$keyword}%"))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('operation-logs.index', [
            'logs' => $logs,
            'isPrivileged' => true,
            'actions' => $actions,
            'filters' => ['action' => array_key_exists($action, $actions) ? $action : '', 'q' => $keyword],
        ]);
    }
}
