<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 操作ログ一覧。ルート側のsupervisor.or.managerミドルウェアにより
 * 経理資材担当・上長のみがアクセスでき、常に全員分のログを表示する。
 */
class OperationLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = OperationLog::with(['staff', 'owner'])->orderByDesc('created_at');

        return view('operation-logs.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'isPrivileged' => true,
        ]);
    }
}
