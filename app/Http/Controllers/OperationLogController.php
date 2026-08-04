<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 操作ログ一覧。資材管理担当者・上長は全員分を、それ以外の一般社員・営業担当は
 * 自分(owner_staff_id)が関わる申請・日報のログのみ閲覧できる。
 */
class OperationLogController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Staff $viewer */
        $viewer = Auth::user();
        $isPrivileged = $viewer->is_procurement_manager || $viewer->is_supervisor;

        $query = OperationLog::with(['staff', 'owner'])->orderByDesc('created_at');

        if (! $isPrivileged) {
            $query->where('owner_staff_id', $viewer->id);
        }

        return view('operation-logs.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'isPrivileged' => $isPrivileged,
        ]);
    }
}
