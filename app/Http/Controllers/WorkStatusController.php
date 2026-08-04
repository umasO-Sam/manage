<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 勤務状況一覧。日付単位で全社員の休暇・休日出勤の状況を表示する。
 * 一般社員・営業担当には申請種別のみ(承認待ち/承認済みの色分けなし)を、
 * 上長・資材管理担当者には承認状況の色分けと、作業日報の登録状況もあわせて表示する。
 */
class WorkStatusController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Staff $viewer */
        $viewer = Auth::user();
        $isPrivileged = $viewer->is_procurement_manager || $viewer->is_supervisor;

        $date = $this->resolveDate($request->query('date'));

        return view('work-status.index', [
            'date' => $date,
            'prevDate' => Carbon::parse($date)->subDay()->format('Y-m-d'),
            'nextDate' => Carbon::parse($date)->addDay()->format('Y-m-d'),
            'staffList' => Staff::orderBy('name')->get(),
            'leaveRequestsByStaff' => $this->buildLeaveRequestsByStaff($date),
            'dailyReportStatusByStaff' => $isPrivileged ? $this->buildDailyReportStatusByStaff($date) : collect(),
            'isPrivileged' => $isPrivileged,
        ]);
    }

    private function resolveDate(?string $date): string
    {
        if ($date) {
            try {
                return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
            } catch (\Exception) {
                // fall through to today
            }
        }

        return now()->format('Y-m-d');
    }

    /**
     * 指定日にかかる全社員の休暇・勤務申請(承認待ち・承認済み)。休日勤務申請の振替休日、
     * 代休申請の代休日は対象日と一致する場合のみ役割を切り替える
     * (PersonalCalendarControllerと同じ考え方)。
     *
     * @return Collection<int, Collection<int, array{request: LeaveRequest, role: string}>>
     */
    private function buildLeaveRequestsByStaff(string $date): Collection
    {
        $requests = LeaveRequest::whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    $q->whereDate('start_date', '<=', $date)
                        ->where(function ($q2) use ($date) {
                            $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
                        });
                })
                    ->orWhereDate('substitute_holiday_date', $date)
                    ->orWhereDate('compensatory_date', $date);
            })
            ->get();

        $byStaff = collect();
        foreach ($requests as $leaveRequest) {
            $end = $leaveRequest->end_date ?? $leaveRequest->start_date;
            $inMainRange = Carbon::parse($date)->betweenIncluded($leaveRequest->start_date, $end);

            $role = 'main';
            if (! $inMainRange && $leaveRequest->substitute_holiday_date?->format('Y-m-d') === $date) {
                $role = 'substitute';
            } elseif (! $inMainRange && $leaveRequest->compensatory_date?->format('Y-m-d') === $date) {
                $role = 'compensatory';
            }

            $byStaff->put(
                $leaveRequest->staff_id,
                $byStaff->get($leaveRequest->staff_id, collect())->push(['request' => $leaveRequest, 'role' => $role])
            );
        }

        return $byStaff;
    }

    /**
     * @return Collection<int, string> staff_id => draft|pending_confirmation|rejected|confirmed
     */
    private function buildDailyReportStatusByStaff(string $date): Collection
    {
        $reports = DailyReport::whereDate('work_date', $date)->get();

        $provisionalReportIds = LaborCost::where('is_provisional', true)
            ->whereNotNull('daily_report_id')
            ->pluck('daily_report_id');

        return $reports->mapWithKeys(function (DailyReport $report) use ($provisionalReportIds) {
            $status = match (true) {
                $report->isRejected() => 'rejected',
                ! $report->isSubmitted() => 'draft',
                $provisionalReportIds->contains($report->id) => 'pending_confirmation',
                default => 'confirmed',
            };

            return [$report->staff_id => $status];
        });
    }
}
