<?php

namespace Tests\Feature;

use App\Mail\LeaveRequestNotificationMail;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 承認済みになった申請を勤怠管理者にも知らせる。勤務状況や有給残に効いてくるため、
 * 決まった時点で把握できるようにする（2026-08-17）。
 */
class LeaveRequestApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const HEADLINE = '申請が承認されました（勤怠管理者へのお知らせ）';

    private function pendingPaidLeave(Staff $applicant, Staff $approver): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0,
            'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }

    public function test_attendance_managers_are_notified_when_a_request_is_approved(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create(['email' => 'applicant@example.com']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        Staff::factory()->create(['is_attendance_manager' => true, 'email' => 'manager@example.com']);
        Staff::factory()->create(['is_administrator' => true, 'email' => 'admin@example.com']);
        $leaveRequest = $this->pendingPaidLeave($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        // 本人には従来どおりの見出しで届く。
        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('applicant@example.com')
            && $mail->headline === '申請が承認されました');
        // 勤怠管理者・administrator にはお知らせとして届く。
        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('manager@example.com')
            && $mail->headline === self::HEADLINE);
        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('admin@example.com')
            && $mail->headline === self::HEADLINE);
    }

    /** 却下は誰の予定も動かないので、勤怠管理者には送らない。 */
    public function test_attendance_managers_are_not_notified_when_a_request_is_rejected(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        Staff::factory()->create(['is_attendance_manager' => true, 'email' => 'manager@example.com']);
        $leaveRequest = $this->pendingPaidLeave($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), [
            'action' => 'reject', 'rejection_reason' => '業務都合',
        ]);

        Mail::assertNotSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('manager@example.com'));
    }

    /**
     * 承認した本人が勤怠管理者を兼ねている場合、その人には送らない
     * (自分の操作の控えが届いても行動を求めないため)。他の勤怠管理者には届く。
     */
    public function test_the_approver_does_not_receive_the_managers_copy(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create([
            'is_supervisor' => true, 'is_attendance_manager' => true, 'email' => 'approver@example.com',
        ]);
        Staff::factory()->create(['is_attendance_manager' => true, 'email' => 'other@example.com']);
        $leaveRequest = $this->pendingPaidLeave($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        Mail::assertNotSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('approver@example.com')
            && $mail->headline === self::HEADLINE);
        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('other@example.com')
            && $mail->headline === self::HEADLINE);
    }

    /**
     * 休日勤務は上長の承認では承認済みにならない（勤怠管理者の確認待ち）。
     * このときは従来どおり「確認をお願いします」が飛び、お知らせは重ねて送らない。
     */
    public function test_holiday_work_still_asks_for_confirmation_instead_of_the_notice(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        Staff::factory()->create(['is_attendance_manager' => true, 'email' => 'manager@example.com']);

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'holiday_work',
            'start_date' => '2026-08-15', 'end_date' => '2026-08-15',
            'substitute_holiday_date' => '2026-08-17',
            'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);

        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('manager@example.com')
            && $mail->headline === '上長承認済みの休日勤務申請の確認をお願いします');
        Mail::assertNotSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('manager@example.com')
            && $mail->headline === self::HEADLINE);
    }

    /**
     * 一括承認でも同じように届く（承認の経路が違っても扱いは変えない）。
     */
    public function test_bulk_approval_also_notifies_the_attendance_managers(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        Staff::factory()->create(['is_attendance_manager' => true, 'email' => 'manager@example.com']);
        $leaveRequest = $this->pendingPaidLeave($applicant, $approver);

        $this->actingAs($approver)->post(route('leave-requests.bulk-approve'), [
            'ids' => [$leaveRequest->id],
        ]);

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->fresh()->status);
        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('manager@example.com')
            && $mail->headline === self::HEADLINE);
    }
}
