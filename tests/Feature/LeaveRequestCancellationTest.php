<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 承認済み申請の取消フロー。
 *
 *   本人が取消申請 → 上長が承認/差し戻し → 勤怠管理者が反映/差し戻し
 *
 * 反映されるまでstatusはapprovedのままで、勤務状況一覧や有給残日数からは消えない。
 */
class LeaveRequestCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedRequest(?Staff $applicant = null, ?Staff $approver = null): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => ($applicant ?? Staff::factory()->create())->id,
            'approver_id' => ($approver ?? Staff::factory()->create(['is_supervisor' => true]))->id,
            'type' => 'paid_leave',
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-20',
            'granularity' => 'full_day',
            'day_count' => 1.0,
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    private function attendanceManager(): Staff
    {
        return Staff::factory()->create(['is_attendance_manager' => true]);
    }

    public function test_the_applicant_can_request_a_cancellation_with_a_reason(): void
    {
        $applicant = Staff::factory()->create();
        $leaveRequest = $this->approvedRequest($applicant);

        $this->actingAs($applicant)->post(route('leave-requests.cancel.request', $leaveRequest), [
            'cancel_reason' => '出張が入ったため出勤します',
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest->refresh();
        $this->assertTrue($leaveRequest->isCancelRequested());
        $this->assertSame('出張が入ったため出勤します', $leaveRequest->cancel_reason);
        $this->assertNotNull($leaveRequest->cancel_requested_at);
        // 取消はまだ確定していないので承認済みのまま。
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertSame('取消申請中', $leaveRequest->statusLabel());
    }

    public function test_a_cancellation_reason_is_required(): void
    {
        $applicant = Staff::factory()->create();
        $leaveRequest = $this->approvedRequest($applicant);

        $this->actingAs($applicant)->post(route('leave-requests.cancel.request', $leaveRequest), [
            'cancel_reason' => '',
        ])->assertSessionHasErrors('cancel_reason');

        $this->assertNull($leaveRequest->fresh()->cancel_status);
    }

    public function test_only_the_applicant_can_request_a_cancellation(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->approvedRequest(null, $approver);
        $stranger = Staff::factory()->create();

        foreach ([$stranger, $approver] as $actor) {
            $this->actingAs($actor)->post(route('leave-requests.cancel.request', $leaveRequest), [
                'cancel_reason' => '代わりに取り消したい',
            ])->assertForbidden();
        }
    }

    public function test_a_pending_request_cannot_be_cancelled_this_way(): void
    {
        $applicant = Staff::factory()->create();
        $leaveRequest = $this->approvedRequest($applicant);
        $leaveRequest->update(['status' => LeaveRequest::STATUS_PENDING]);

        // 承認前は既存の「取下げ」を使う。
        $this->actingAs($applicant)->post(route('leave-requests.cancel.request', $leaveRequest), [
            'cancel_reason' => '取り消したい',
        ])->assertForbidden();
    }

    public function test_the_supervisor_approving_a_cancellation_sends_it_to_the_attendance_manager(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->approvedRequest(null, $approver);
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_REQUESTED, 'cancel_reason' => '出勤するため']);

        $this->actingAs($approver)->put(route('leave-requests.cancel.decide', $leaveRequest), [
            'action' => 'approve',
        ])->assertRedirect(route('leave-requests.approvals'));

        $leaveRequest->refresh();
        $this->assertTrue($leaveRequest->isCancelPendingReflection());
        $this->assertSame('取消の反映確認中', $leaveRequest->statusLabel());
        // 上長が認めただけでは取り消されない。
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
    }

    public function test_the_supervisor_can_send_a_cancellation_back(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->approvedRequest(null, $approver);
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_REQUESTED, 'cancel_reason' => '出勤するため']);

        $this->actingAs($approver)->put(route('leave-requests.cancel.decide', $leaveRequest), [
            'action' => 'reject',
            'cancel_rejection_reason' => '別の日に振り替えてください',
        ])->assertRedirect(route('leave-requests.approvals'));

        $leaveRequest->refresh();
        $this->assertNull($leaveRequest->cancel_status, '承認済みに戻ること');
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertSame('別の日に振り替えてください', $leaveRequest->cancel_rejection_reason);
        // 差し戻されたら本人はもう一度取消を申請できる。
        $this->assertTrue($leaveRequest->canRequestCancel());
    }

    public function test_sending_back_requires_a_reason(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->approvedRequest(null, $approver);
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_REQUESTED]);

        $this->actingAs($approver)->put(route('leave-requests.cancel.decide', $leaveRequest), [
            'action' => 'reject',
        ])->assertSessionHasErrors('cancel_rejection_reason');

        $this->assertTrue($leaveRequest->fresh()->isCancelRequested());
    }

    public function test_the_attendance_manager_reflecting_the_cancellation_finalises_it(): void
    {
        $leaveRequest = $this->approvedRequest();
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_PENDING_REFLECTION, 'cancel_reason' => '出勤するため']);

        $this->actingAs($this->attendanceManager())
            ->put(route('leave-requests.cancel.reflect', $leaveRequest), ['action' => 'reflect'])
            ->assertRedirect(route('leave-requests.cancellations'));

        $leaveRequest->refresh();
        $this->assertTrue($leaveRequest->isCancelled());
        $this->assertNull($leaveRequest->cancel_status);
        $this->assertNotNull($leaveRequest->cancelled_at);
        $this->assertSame('取消済み（承認後）', $leaveRequest->statusLabel());
    }

    public function test_the_attendance_manager_can_send_the_cancellation_back(): void
    {
        $leaveRequest = $this->approvedRequest();
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_PENDING_REFLECTION]);

        $this->actingAs($this->attendanceManager())
            ->put(route('leave-requests.cancel.reflect', $leaveRequest), [
                'action' => 'send_back',
                'cancel_rejection_reason' => '有給の取消ではなく振替の申請を出してください',
            ])->assertRedirect(route('leave-requests.cancellations'));

        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status, '取り消されず承認済みのまま');
        $this->assertNull($leaveRequest->cancel_status);
        $this->assertSame('有給の取消ではなく振替の申請を出してください', $leaveRequest->cancel_rejection_reason);
    }

    public function test_a_non_attendance_manager_cannot_reflect(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->approvedRequest(null, $approver);
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_PENDING_REFLECTION]);

        foreach ([$approver, Staff::factory()->create()] as $actor) {
            $this->actingAs($actor)
                ->put(route('leave-requests.cancel.reflect', $leaveRequest), ['action' => 'reflect'])
                ->assertForbidden();
        }

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->fresh()->status);
    }

    public function test_the_reflection_screen_is_limited_to_attendance_managers(): void
    {
        $this->actingAs(Staff::factory()->create(['is_supervisor' => true]))
            ->get(route('leave-requests.cancellations'))->assertForbidden();

        $this->actingAs($this->attendanceManager())
            ->get(route('leave-requests.cancellations'))->assertOk();

        // administrator はすべての機能を使える。
        $this->actingAs(Staff::factory()->create(['is_administrator' => true]))
            ->get(route('leave-requests.cancellations'))->assertOk();
    }

    public function test_the_reflection_screen_lists_only_requests_waiting_for_reflection(): void
    {
        $waiting = $this->approvedRequest();
        $waiting->update(['cancel_status' => LeaveRequest::CANCEL_PENDING_REFLECTION, 'cancel_reason' => '反映待ちの理由']);

        $stillWithSupervisor = $this->approvedRequest();
        $stillWithSupervisor->update(['cancel_status' => LeaveRequest::CANCEL_REQUESTED, 'cancel_reason' => '上長判断中の理由']);

        $this->actingAs($this->attendanceManager())
            ->get(route('leave-requests.cancellations'))
            ->assertOk()
            ->assertSee('反映待ちの理由')
            ->assertDontSee('上長判断中の理由');
    }

    /**
     * 取消手続き中も承認済み扱いを続ける。勤務状況一覧・個人カレンダー・有給残日数が
     * status=approved で判定しているため、途中で消えると「取り消せるか未確定なのに
     * 休みが消える」ことになる。
     */
    public function test_a_request_under_cancellation_still_counts_as_approved(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $leaveRequest = $this->approvedRequest($applicant);

        $consumedWhenApproved = $applicant->fresh()->paidLeaveBalance()['remainingTotal'];

        foreach ([LeaveRequest::CANCEL_REQUESTED, LeaveRequest::CANCEL_PENDING_REFLECTION] as $cancelStatus) {
            $leaveRequest->update(['cancel_status' => $cancelStatus]);
            $this->assertSame(
                $consumedWhenApproved,
                $applicant->fresh()->paidLeaveBalance()['remainingTotal'],
                "cancel_status={$cancelStatus} の間は有給が戻らないこと"
            );
            $this->actingAs($applicant)->get(route('work-status.index'))->assertOk()->assertSee('有休');
        }

        // 反映して初めて有給が戻る。
        $leaveRequest->update(['status' => LeaveRequest::STATUS_CANCELLED, 'cancel_status' => null]);
        $this->assertSame($consumedWhenApproved + 1.0, $applicant->fresh()->paidLeaveBalance()['remainingTotal']);
    }

    public function test_each_step_is_written_to_the_operation_log(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->approvedRequest($applicant, $approver);

        $this->actingAs($applicant)->post(route('leave-requests.cancel.request', $leaveRequest), ['cancel_reason' => '出勤するため']);
        $this->actingAs($approver)->put(route('leave-requests.cancel.decide', $leaveRequest), ['action' => 'approve']);
        $this->actingAs($this->attendanceManager())->put(route('leave-requests.cancel.reflect', $leaveRequest), ['action' => 'reflect']);

        $actions = OperationLog::where('subject_type', LeaveRequest::class)
            ->where('subject_id', $leaveRequest->id)->pluck('action')->all();
        $this->assertContains(OperationLog::ACTION_LEAVE_REQUEST_CANCEL_REQUEST, $actions);
        $this->assertContains(OperationLog::ACTION_LEAVE_REQUEST_CANCEL_APPROVE, $actions);
        $this->assertContains(OperationLog::ACTION_LEAVE_REQUEST_CANCEL_REFLECT, $actions);
    }

    public function test_the_supervisor_sees_cancel_requests_on_the_approvals_screen(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->approvedRequest(null, $approver);
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_REQUESTED, 'cancel_reason' => '出張が入ったため']);

        $this->actingAs($approver)->get(route('leave-requests.approvals'))
            ->assertOk()
            ->assertSee('承認済み申請の取消申請')
            ->assertSee('出張が入ったため');

        // 承認バッジにも数える。
        $this->assertSame(1, $approver->fresh()->pendingApprovalsCount());
    }

    public function test_the_attendance_manager_can_open_a_request_they_are_asked_to_reflect(): void
    {
        $manager = $this->attendanceManager();
        $leaveRequest = $this->approvedRequest();

        // 取消手続きに入っていない他人の申請は見られない。
        $this->actingAs($manager)->get(route('leave-requests.show', $leaveRequest))->assertForbidden();

        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_PENDING_REFLECTION]);
        $this->actingAs($manager)->get(route('leave-requests.show', $leaveRequest))->assertOk();
    }

    public function test_the_reflection_badge_counts_only_for_attendance_managers(): void
    {
        $leaveRequest = $this->approvedRequest();
        $leaveRequest->update(['cancel_status' => LeaveRequest::CANCEL_PENDING_REFLECTION]);

        $this->assertSame(1, $this->attendanceManager()->pendingCancelReflectionCount());
        $this->assertSame(0, Staff::factory()->create(['is_supervisor' => true])->pendingCancelReflectionCount());
    }
}
