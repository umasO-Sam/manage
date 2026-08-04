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
}
