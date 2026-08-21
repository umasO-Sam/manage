<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 承認済みの休日勤務申請・代休申請の変更フロー。
 *
 * 出勤した日は動かせず、変えられるのは振替休日(取らない選択を含む)と代休日だけ。
 * 変更申請を出すと承認済みから承認待ちへ戻り、上長 → 勤怠管理者の順に決裁し直す。
 */
class LeaveRequestAmendmentTest extends TestCase
{
    use RefreshDatabase;

    private Staff $applicant;

    private Staff $approver;

    private Staff $attendanceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicant = Staff::factory()->create(['name' => '申請太郎', 'paid_leave_granted_current_year' => 10]);
        $this->approver = Staff::factory()->create(['is_supervisor' => true, 'name' => '上長次郎']);
        $this->attendanceManager = Staff::factory()->create(['is_attendance_manager' => true, 'name' => '勤怠三郎']);
    }

    private function approvedHolidayWork(): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => $this->applicant->id, 'approver_id' => $this->approver->id,
            'type' => 'holiday_work', 'start_date' => '2026-08-22', 'end_date' => '2026-08-22',
            'order_no' => 'A-1', 'work_location' => '本社',
            'substitute_holiday_date' => '2026-08-24',
            'status' => LeaveRequest::STATUS_APPROVED, 'approved_at' => now(), 'supervisor_approved_at' => now(),
        ]);
    }

    private function approvedCompensatoryLeave(): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => $this->applicant->id, 'approver_id' => $this->approver->id,
            'type' => 'compensatory_leave', 'start_date' => '2026-08-22', 'end_date' => '2026-08-22',
            'actual_worked_hours' => 8, 'compensatory_date' => '2026-08-26',
            'status' => LeaveRequest::STATUS_APPROVED, 'approved_at' => now(), 'supervisor_approved_at' => now(),
        ]);
    }

    public function test_the_whole_flow_moves_the_substitute_holiday(): void
    {
        $leaveRequest = $this->approvedHolidayWork();

        // 1. 本人が変更申請 → 承認待ちに戻る
        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31',
            'amend_reason' => '納期が動いたため',
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::AMEND_REQUESTED, $leaveRequest->amend_status);
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leaveRequest->status);
        $this->assertSame('変更の承認待ち', $leaveRequest->statusLabel());
        // 反映されるまで元の振替休日は動かさない。
        $this->assertSame('2026-08-24', $leaveRequest->substitute_holiday_date->format('Y-m-d'));

        // 2. 上長が承認 → 勤怠管理者待ち
        $this->actingAs($this->approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve'])
            ->assertRedirect(route('leave-requests.approvals'));

        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::AMEND_PENDING_REFLECTION, $leaveRequest->amend_status);
        $this->assertSame(LeaveRequest::STATUS_PENDING_ATTENDANCE, $leaveRequest->status);
        $this->assertSame('2026-08-24', $leaveRequest->substitute_holiday_date->format('Y-m-d'));

        // 3. 勤怠管理者が承認 → 反映されて承認済みに戻る
        $this->actingAs($this->attendanceManager)
            ->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'approve'])
            ->assertRedirect(route('leave-requests.cancellations'));

        $leaveRequest->refresh();
        $this->assertNull($leaveRequest->amend_status);
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertSame('2026-08-31', $leaveRequest->substitute_holiday_date->format('Y-m-d'));
        $this->assertNotNull($leaveRequest->amended_at);
        $this->assertNull($leaveRequest->amend_substitute_holiday_date);
    }

    public function test_the_substitute_holiday_can_be_dropped_altogether(): void
    {
        $leaveRequest = $this->approvedHolidayWork();

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'no_substitute_needed' => '1',
            'amend_reason' => '業務繁忙のため振り替えない',
        ])->assertRedirect(route('leave-requests.index'));

        $this->actingAs($this->approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);
        $this->actingAs($this->attendanceManager)
            ->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'approve']);

        $leaveRequest->refresh();
        $this->assertTrue($leaveRequest->no_substitute_needed);
        $this->assertNull($leaveRequest->substitute_holiday_date);
    }

    public function test_the_compensatory_date_can_be_moved(): void
    {
        $leaveRequest = $this->approvedCompensatoryLeave();

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'compensatory_date' => '2026-09-02',
            'amend_reason' => '当日出社が必要になったため',
        ])->assertRedirect(route('leave-requests.index'));

        // 代休申請も、変更のときは勤怠管理者の確認を通す。
        $this->actingAs($this->approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);
        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING_ATTENDANCE, $leaveRequest->status);

        $this->actingAs($this->attendanceManager)
            ->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'approve']);

        $leaveRequest->refresh();
        $this->assertSame('2026-09-02', $leaveRequest->compensatory_date->format('Y-m-d'));
        $this->assertSame('2026-08-22', $leaveRequest->start_date->format('Y-m-d'), '出勤日は変わらないこと');
    }

    public function test_a_supervisor_send_back_keeps_the_original_content(): void
    {
        $leaveRequest = $this->approvedHolidayWork();

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31', 'amend_reason' => '変更したい',
        ]);
        $this->actingAs($this->approver)->put(route('leave-requests.decide', $leaveRequest), [
            'action' => 'reject', 'rejection_reason' => 'その日は人手が足りない',
        ]);

        $leaveRequest->refresh();
        $this->assertNull($leaveRequest->amend_status);
        // 元の承認済みの内容がそのまま残る(却下にはならない)。
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertSame('2026-08-24', $leaveRequest->substitute_holiday_date->format('Y-m-d'));
        $this->assertSame('その日は人手が足りない', $leaveRequest->amend_rejection_reason);
        // 差し戻されたらもう一度申請できる。
        $this->assertTrue($leaveRequest->canRequestAmend());
    }

    public function test_an_attendance_manager_send_back_keeps_the_original_content(): void
    {
        $leaveRequest = $this->approvedHolidayWork();

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31', 'amend_reason' => '変更したい',
        ]);
        $this->actingAs($this->approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);
        $this->actingAs($this->attendanceManager)->put(route('leave-requests.attendance.decide', $leaveRequest), [
            'action' => 'reject', 'rejection_reason' => '振替は同一週内にしてください',
        ]);

        $leaveRequest->refresh();
        $this->assertNull($leaveRequest->amend_status);
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leaveRequest->status);
        $this->assertSame('2026-08-24', $leaveRequest->substitute_holiday_date->format('Y-m-d'));
        $this->assertNull($leaveRequest->amended_at);
    }

    /**
     * 誰がいつ何をしたかを操作ログだけで追えること。
     * 申請 → 上長承認 → 管理承認 → 変更申請 → 変更の上長承認 → 変更の管理承認 の6段。
     */
    public function test_every_step_is_written_to_the_operation_log(): void
    {
        // 申請から通す(申請・上長承認・勤怠管理者承認のログもここで残る)。
        $this->actingAs($this->applicant)->post(route('leave-requests.store'), [
            'type' => 'holiday_work', 'approver_id' => $this->approver->id,
            'start_date' => '2026-08-22', 'order_no' => 'A-1', 'work_location' => '本社',
            'substitute_holiday_date' => '2026-08-24',
        ]);
        $leaveRequest = LeaveRequest::sole();

        $this->actingAs($this->approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);
        $this->actingAs($this->attendanceManager)
            ->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'approve']);

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31', 'amend_reason' => '納期が動いたため',
        ]);
        $this->actingAs($this->approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve']);
        $this->actingAs($this->attendanceManager)
            ->put(route('leave-requests.attendance.decide', $leaveRequest), ['action' => 'approve']);

        $logs = OperationLog::where('subject_type', LeaveRequest::class)
            ->where('subject_id', $leaveRequest->id)->orderBy('id')->get();

        $this->assertSame([
            OperationLog::ACTION_LEAVE_REQUEST_CREATE,
            OperationLog::ACTION_LEAVE_REQUEST_APPROVE,
            OperationLog::ACTION_LEAVE_REQUEST_ATTENDANCE_APPROVE,
            OperationLog::ACTION_LEAVE_REQUEST_AMEND_REQUEST,
            OperationLog::ACTION_LEAVE_REQUEST_AMEND_APPROVE,
            OperationLog::ACTION_LEAVE_REQUEST_AMEND_REFLECT,
        ], $logs->pluck('action')->all());

        // 誰が行ったか(申請者・上長・勤怠管理者)がそれぞれ残る。
        $this->assertSame([
            $this->applicant->id, $this->approver->id, $this->attendanceManager->id,
            $this->applicant->id, $this->approver->id, $this->attendanceManager->id,
        ], $logs->pluck('staff_id')->all());

        // いつ行ったかと、変更内容(旧→新)・理由も残る。
        $this->assertTrue($logs->every(fn (OperationLog $log) => $log->created_at !== null));
        $this->assertStringContainsString('振替休日 2026/08/24 → 2026/08/31', $logs[3]->description);
        $this->assertStringContainsString('納期が動いたため', $logs[3]->description);
        $this->assertStringContainsString('振替休日 2026/08/24 → 2026/08/31', $logs[5]->description);

        // 申請詳細の「対応履歴」からも同じ内容が読める。
        $this->actingAs($this->approver)->get(route('leave-requests.show', $leaveRequest))
            ->assertOk()
            ->assertSee('変更を申請')
            ->assertSee('変更を承認（上長）')
            ->assertSee('変更を反映（勤怠管理者）')
            ->assertSee('振替休日 2026/08/24 → 2026/08/31');
    }

    /**
     * 変更以外の申請・承認のログにも、対象日と申請内容を残す。
     * 一覧(操作ログ画面)だけを見て「何の申請の話か」が分かるようにするため。
     */
    public function test_every_leave_request_log_carries_the_date_and_the_content(): void
    {
        // 休日勤務: 申請 → 上長承認 → 勤怠管理者承認
        $this->actingAs($this->applicant)->post(route('leave-requests.store'), [
            'type' => 'holiday_work', 'approver_id' => $this->approver->id,
            'start_date' => '2026-08-22', 'order_no' => 'A-1', 'work_location' => '本社',
            'substitute_holiday_date' => '2026-08-24',
        ]);
        $holidayWork = LeaveRequest::sole();

        $this->actingAs($this->approver)->put(route('leave-requests.decide', $holidayWork), ['action' => 'approve']);
        $this->actingAs($this->attendanceManager)
            ->put(route('leave-requests.attendance.decide', $holidayWork), ['action' => 'approve']);

        $expected = '休日勤務申請 2026/08/22（注番 A-1／本社／振休 2026/08/24）';
        foreach (OperationLog::where('subject_id', $holidayWork->id)->get() as $log) {
            $this->assertStringContainsString($expected, (string) $log->description, "{$log->actionLabel()}に対象日と内容がありません。");
        }

        // 有給休暇: 粒度・午前午後・日数まで残す
        $this->actingAs($this->applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave', 'approver_id' => $this->approver->id,
            'start_date' => '2026-08-25', 'granularity' => 'half_day', 'half_day_period' => 'pm',
        ]);
        $paidLeave = LeaveRequest::where('type', 'paid_leave')->sole();

        $this->assertStringContainsString(
            '有給休暇 2026/08/25（半日／午後(PM)／0.5日）',
            (string) OperationLog::where('subject_id', $paidLeave->id)->sole()->description
        );

        // 却下の理由も、何の申請かと並べて残す
        $this->actingAs($this->approver)->put(route('leave-requests.decide', $paidLeave), [
            'action' => 'reject', 'rejection_reason' => 'その日は手が足りない',
        ]);
        $rejected = OperationLog::where('subject_id', $paidLeave->id)
            ->where('action', OperationLog::ACTION_LEAVE_REQUEST_REJECT)->sole();
        $this->assertStringContainsString('有給休暇 2026/08/25', (string) $rejected->description);
        $this->assertStringContainsString('却下理由: その日は手が足りない', (string) $rejected->description);
    }

    /** 代休申請は勤務時間と代休日、取消のログにも同じ一文が付く。 */
    public function test_the_summary_is_also_kept_on_compensatory_and_cancellation_logs(): void
    {
        $leaveRequest = $this->approvedCompensatoryLeave();

        $this->actingAs($this->applicant)->post(route('leave-requests.cancel.request', $leaveRequest), [
            'cancel_reason' => '出社することになったため',
        ]);

        $log = OperationLog::where('subject_id', $leaveRequest->id)
            ->where('action', OperationLog::ACTION_LEAVE_REQUEST_CANCEL_REQUEST)->sole();

        $this->assertStringContainsString('代休申請 2026/08/22（勤務8時間／代休 2026/08/26）', (string) $log->description);
        $this->assertStringContainsString('取消理由: 出社することになったため', (string) $log->description);
    }

    public function test_the_work_date_and_other_types_cannot_be_amended(): void
    {
        // 出勤日は送っても無視される(変更対象に入っていない)。
        $leaveRequest = $this->approvedHolidayWork();
        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'start_date' => '2026-08-23',
            'substitute_holiday_date' => '2026-08-31',
            'amend_reason' => '出勤日も変えたい',
        ]);
        $this->assertSame('2026-08-22', $leaveRequest->fresh()->start_date->format('Y-m-d'));

        // 有給休暇など、休出・代休以外は変更申請の対象外。
        $paidLeave = LeaveRequest::create([
            'staff_id' => $this->applicant->id, 'approver_id' => $this->approver->id,
            'type' => 'paid_leave', 'start_date' => '2026-08-25', 'end_date' => '2026-08-25',
            'granularity' => 'full_day', 'day_count' => 1.0, 'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        $this->assertFalse($paidLeave->canRequestAmend());
        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $paidLeave), [
            'amend_reason' => '変更したい',
        ])->assertForbidden();
    }

    public function test_only_the_applicant_can_amend_and_only_once_at_a_time(): void
    {
        $leaveRequest = $this->approvedHolidayWork();

        // 他人(上長でも)は出せない。
        $this->actingAs($this->approver)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31', 'amend_reason' => '代わりに変更',
        ])->assertForbidden();

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31', 'amend_reason' => '変更したい',
        ]);

        // 決裁中に重ねて出すことはできない。
        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-09-01', 'amend_reason' => 'やっぱりこちら',
        ])->assertForbidden();
    }

    public function test_an_unchanged_or_empty_amendment_is_rejected(): void
    {
        $leaveRequest = $this->approvedHolidayWork();

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-24', 'amend_reason' => '同じ日',
        ])->assertSessionHasErrors('substitute_holiday_date');

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'amend_reason' => '日付を書かない',
        ])->assertSessionHasErrors('substitute_holiday_date');

        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31',
        ])->assertSessionHasErrors('amend_reason');

        $this->assertNull($leaveRequest->fresh()->amend_status);
    }

    /** 変更申請は内容を見てから判断してほしいので、一括承認には含めない。 */
    public function test_an_amendment_is_not_included_in_bulk_approval(): void
    {
        $leaveRequest = $this->approvedHolidayWork();
        $this->actingAs($this->applicant)->post(route('leave-requests.amend.request', $leaveRequest), [
            'substitute_holiday_date' => '2026-08-31', 'amend_reason' => '変更したい',
        ]);

        $this->actingAs($this->approver)->post(route('leave-requests.bulk-approve'), ['ids' => [$leaveRequest->id]])
            ->assertSessionHasErrors('ids');

        $this->assertSame(LeaveRequest::AMEND_REQUESTED, $leaveRequest->fresh()->amend_status);

        // 一覧にはチェックボックスの代わりに「変更申請」と旧→新を出す。
        $this->actingAs($this->approver)->get(route('leave-requests.approvals'))
            ->assertOk()
            ->assertSee('変更申請')
            ->assertSee('振替休日 2026/08/24 → 2026/08/31');
    }
}
