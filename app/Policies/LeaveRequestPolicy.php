<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\Staff;

class LeaveRequestPolicy
{
    /**
     * Every staff member may raise a new request.
     */
    public function create(Staff $staff): bool
    {
        return true;
    }

    /**
     * Only the applicant or the designated approver may view a request's detail.
     */
    public function view(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        // 勤怠管理者は状態を問わず中身を見られる。取消の反映確認・休日勤務の承認・承認済みの
        // お知らせがいずれも勤怠管理者へ飛ぶため、対応が済んだあとに通知のリンクを開いただけで
        // 403になっていた。判断材料として見るだけで、操作できるかは各policyが別に判定する。
        //
        // 上長・役員・資金管理者・administratorも同様に閲覧できる。勤務状況一覧のセルから
        // 「誰がどの申請で休むのか」をその場で確かめるため(2026-08-21)。
        if ($staff->canViewOthersLeaveRequests()) {
            return true;
        }

        return $staff->id === $leaveRequest->staff_id || $staff->id === $leaveRequest->approver_id;
    }

    /**
     * Only the applicant may withdraw their own request, and only while it is
     * still awaiting a decision.
     */
    public function withdraw(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->id === $leaveRequest->staff_id && $leaveRequest->isWithdrawable();
    }

    /**
     * Only the designated approver may approve/reject, and only while pending.
     */
    public function decide(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->id === $leaveRequest->approver_id && $leaveRequest->isPending();
    }

    /**
     * 上長が承認した休日勤務を確定させてよいかは勤怠管理者が判断する。
     * 差し戻す場合は却下として本人・上長の双方に返す。
     */
    public function attendanceDecide(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->canManageAttendance() && $leaveRequest->isPendingAttendance();
    }

    /**
     * 承認済みの休日勤務・代休の変更申請も、本人だけが言い出せる。
     */
    public function requestAmend(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->id === $leaveRequest->staff_id && $leaveRequest->canRequestAmend();
    }

    /**
     * 承認済みになったあとの取消は本人だけが言い出せる。
     */
    public function requestCancel(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->id === $leaveRequest->staff_id && $leaveRequest->canRequestCancel();
    }

    /**
     * 取消を認めるかどうかは、元の申請を承認した上長が判断する。
     */
    public function decideCancel(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->id === $leaveRequest->approver_id && $leaveRequest->isCancelRequested();
    }

    /**
     * 上長が認めた取消を実際に反映してよいかは勤怠管理者が判断する。
     * 法律やルールに照らして、別の申請を出し直してもらう差し戻しもできる。
     */
    public function reflectCancel(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->canManageAttendance() && $leaveRequest->isCancelPendingReflection();
    }
}
