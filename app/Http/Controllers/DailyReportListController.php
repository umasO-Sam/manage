<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use App\Services\WorkTimeComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 作業日報一覧。勤務状況一覧(日付×全社員のカレンダー俯瞰)とは別に、提出済み・下書きを
 * 問わず全ての作業日報を表形式で検索・絞り込みできる画面。資材管理担当者・上長は
 * 全員分を、それ以外の一般社員・営業担当は自分の分のみ閲覧できる。
 */
class DailyReportListController extends Controller
{
    public function index(Request $request, WorkTimeComplianceService $compliance): View
    {
        /** @var Staff $viewer */
        $viewer = Auth::user();
        $isPrivileged = $viewer->is_procurement_manager || $viewer->is_supervisor;

        [$defaultStart, $defaultEnd] = $compliance->monthPeriod(Carbon::today());
        $startDate = $this->parseDate($request->query('start_date')) ?? $defaultStart->format('Y-m-d');
        $endDate = $this->parseDate($request->query('end_date')) ?? $defaultEnd->format('Y-m-d');
        $staffId = $isPrivileged ? $request->query('staff_id') : null;
        $status = $request->query('status');

        $query = DailyReport::whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->with('staff');

        if ($isPrivileged) {
            if ($staffId) {
                $query->where('staff_id', $staffId);
            }
        } else {
            $query->where('staff_id', $viewer->id);
        }

        $provisionalReportIds = LaborCost::where('is_provisional', true)
            ->whereNotNull('daily_report_id')
            ->pluck('daily_report_id');

        $reports = $query->orderByDesc('work_date')->get()
            ->map(function (DailyReport $report) use ($provisionalReportIds) {
                $report->statusKey = match (true) {
                    $report->isRejected() => 'rejected',
                    ! $report->isSubmitted() => 'draft',
                    $provisionalReportIds->contains($report->id) => 'pending_confirmation',
                    default => 'confirmed',
                };

                return $report;
            });

        if ($status) {
            $reports = $reports->filter(fn (DailyReport $r) => $r->statusKey === $status)->values();
        }

        return view('daily-reports.list.index', [
            'reports' => $reports,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'staffId' => $staffId,
            'status' => $status,
            'isPrivileged' => $isPrivileged,
            'staffList' => $isPrivileged ? Staff::orderedForRoster()->get() : collect(),
        ]);
    }

    private function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
