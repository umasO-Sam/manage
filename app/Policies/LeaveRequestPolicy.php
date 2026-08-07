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
        // 取消の反映確認を任された勤怠管理者も、判断材料として中身を見る必要がある。
        if ($staff->canManageAttendance() && $leaveRequest->cancel_status !== null) {
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
        return $staff->id === $leaveRequest->staff_id && $leaveRequest->isPending();
    }

    /**
     * Only the designated approver may approve/reject, and only while pending.
     */
    public function decide(Staff $staff, LeaveRequest $leaveRequest): bool
    {
        return $staff->id === $leaveRequest->approver_id && $leaveRequest->isPending();
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
