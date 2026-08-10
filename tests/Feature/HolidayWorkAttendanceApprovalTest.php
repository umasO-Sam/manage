<?php

namespace Tests\Feature;

use App\Mail\LeaveRequestNotificationMail;
use App\Models\LeaveRequest;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 休日勤務申請の2段階承認。上長が承認しただけでは確定せず、勤怠管理者が確認して
 * 初めて承認済みになる(法定休日の割増や振替の成立に関わるため)。
 */
class HolidayWorkAttendanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(): Staff
    {
        return Staff::factory()->create(['is_supervisor' => true, 'name' => '上長太郎']);
    }

    private function attendanceManager(): Staff
    {
        return Staff::factory()->create(['is_attendance_manager' => true, 'name' => '勤怠花子']);
    }

    /** 上長の承認待ちの休日勤務申請を作る。 */
    private function createHolidayWork(Staff $applicant, Staff $approver): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => $applicant->id,
            'approver_id' => $approver->id,
            'type' => 'holiday_work',
            'status' => LeaveRequest::STATUS_PENDING,
            'start_date' => '2026-08-15',
            'end_date' => '2026-08-15',
            'order_no' => 'A-1',
            'work_location' => '本社',
            'substitute_holiday_date' => '2026-08-17',
        ]);
    }

    public function test_supervisor_approval_leaves_the_request_waiting_for_the_attendance_manager(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = $this->supervisor();
        $manager = $this->attendanceManager();
        $leaveRequest = $this->createHolidayWork($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve'])
            ->assertRedirect(route('leave-requests.approvals'));

        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING_ATTENDANCE, $leaveRequest->status);
        $this->assertFalse($leaveRequest->isApproved(), '上長承認だけでは承認済みにしない');
        $this->assertNotNull($leaveRequest->supervisor_approved_at);
        $this->assertSame('承認待ち（勤怠管理者）', $leaveRequest->statusLabel());

        // 勤怠管理者に確認依頼が届く。
        Mail::assertSent(LeaveRequestNotificationMail::class,
            fn ($mail) => $mail->hasTo($manager->email));
    }

    /** 休日勤務以外は従来どおり、上長の承認でそのまま承認済みになる。 */
    public function test_other_types_are_still_approved_by_the_supervisor_alone(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = $this->supervisor();

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $applicant->id, 'approver_id' => $approver->id,
            'type' => 'paid_leave', 'status' => LeaveRequest::STATUS_PENDING,
            'start_date' => '2026-08-15', 'end_date' => '2026-08-15',
            'granularity' => 'full_day', 'day_count' => 1.0,
        ]);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->fresh()->status);
    }

    public function test_bulk_approval_also_routes_holiday_work_to_the_attendance_manager(): void
    {
        Mail::fake();
        $approver = $this->supervisor();
        $this->attendanceManager();
        $leaveRequest = $this->createHolidayWork(Staff::factory()->create(), $approver);

        $this->actingAs($approver)->post(route('leave-requests.bulk-approve'), ['ids' => [$leaveRequest->id]]);

        $this->assertSame(LeaveRequest::STATUS_PENDING_ATTENDANCE, $leaveRequest->fresh()->status);
    }

    public function test_the_attendance_manager_approval_makes_it_approved(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = $this->supervisor();
        $manager = $this->attendanceManager();
        $leaveRequest = $this->createHolidayWork($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        $this->actingAs($manager)->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'approve'])
            ->assertRedirect(route('leave-requests.cancellations'));

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->fresh()->status);
        $this->assertDatabaseHas('operation_logs', ['action' => OperationLog::ACTION_LEAVE_REQUEST_ATTENDANCE_APPROVE]);

        Mail::assertSent(LeaveRequestNotificationMail::class,
            fn ($mail) => $mail->hasTo($applicant->email));
    }

    /** 差し戻しは却下として扱い、本人だけでなく承認した上長にも伝える。 */
    public function test_a_send_back_notifies_both_the_applicant_and_the_supervisor(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = $this->supervisor();
        $manager = $this->attendanceManager();
        $leaveRequest = $this->createHolidayWork($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);
        Mail::fake(); // 上長承認時の通知と混ざらないよう入れ直す

        $this->actingAs($manager)->put(route('leave-requests.attendance.decide', $leaveRequest), [
            'action' => 'reject',
            'rejection_reason' => '振替休日が所定の期間を超えています',
        ])->assertRedirect(route('leave-requests.cancellations'));

        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::STATUS_REJECTED, $leaveRequest->status);
        $this->assertSame('振替休日が所定の期間を超えています', $leaveRequest->rejection_reason);

        Mail::assertSent(LeaveRequestNotificationMail::class,
            fn ($mail) => $mail->hasTo($applicant->email));
        Mail::assertSent(LeaveRequestNotificationMail::class,
            fn ($mail) => $mail->hasTo($approver->email));
    }

    public function test_a_send_back_requires_a_reason(): void
    {
        Mail::fake();
        $approver = $this->supervisor();
        $manager = $this->attendanceManager();
        $leaveRequest = $this->createHolidayWork(Staff::factory()->create(), $approver);
        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        $this->actingAs($manager)->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'reject'])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(LeaveRequest::STATUS_PENDING_ATTENDANCE, $leaveRequest->fresh()->status);
    }

    public function test_a_staff_member_without_the_flag_cannot_decide(): void
    {
        Mail::fake();
        $approver = $this->supervisor();
        $this->attendanceManager();
        $leaveRequest = $this->createHolidayWork(Staff::factory()->create(), $approver);
        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        // 上長であっても勤怠管理者フラグが無ければ確認できない。
        $this->actingAs($approver)->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'approve'])
            ->assertForbidden();

        $this->assertSame(LeaveRequest::STATUS_PENDING_ATTENDANCE, $leaveRequest->fresh()->status);
    }

    public function test_it_appears_on_the_attendance_manager_screen_and_in_the_badge_count(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create(['name' => '休出一郎']);
        $approver = $this->supervisor();
        $manager = $this->attendanceManager();
        $leaveRequest = $this->createHolidayWork($applicant, $approver);
        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        $this->actingAs($manager)->get(route('leave-requests.cancellations'))
            ->assertOk()
            ->assertSee('休出一郎')
            ->assertSee('休日勤務の承認');

        $this->assertSame(1, $manager->fresh()->pendingCancelReflectionCount());
    }

    /**
     * 勤怠管理者待ちの間も勤務状況一覧には「承認待ち」として出す
     * (上長が通した予定を現場が把握できるようにするため)。承認済みにはしない。
     */
    public function test_it_is_shown_as_pending_on_the_work_status_screen(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create(['name' => '休出一郎']);
        $approver = $this->supervisor();
        $leaveRequest = $this->createHolidayWork($applicant, $approver);
        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        $this->actingAs($approver)->get(route('work-status.index', ['month' => '2026-08']))
            ->assertOk()
            ->assertSee('休出');
    }

    /** 決裁が終わるまでは、勤怠管理者待ちでも本人が取り下げられる。 */
    public function test_the_applicant_can_still_withdraw_while_waiting_for_the_attendance_manager(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = $this->supervisor();
        $leaveRequest = $this->createHolidayWork($applicant, $approver);
        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        $this->actingAs($applicant)->delete(route('leave-requests.withdraw', $leaveRequest))
            ->assertRedirect(route('leave-requests.index'));

        $this->assertSame(LeaveRequest::STATUS_WITHDRAWN, $leaveRequest->fresh()->status);
    }
}
