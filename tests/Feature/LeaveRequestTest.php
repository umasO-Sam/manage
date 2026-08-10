<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_staff_can_view_the_create_form(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('leave-requests.create'))->assertOk();
    }

    public function test_paid_leave_full_day_is_created_with_day_count_one(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'granularity' => 'full_day',
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::first();
        $this->assertSame('paid_leave', $leaveRequest->type);
        $this->assertSame(1.0, (float) $leaveRequest->day_count);
        $this->assertSame('pending', $leaveRequest->status);
        $this->assertSame($approver->id, $leaveRequest->approver_id);
    }

    public function test_paid_leave_hours_granularity_computes_quarter_day(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'granularity' => 'hours',
            'half_day_period' => 'am',
        ]);

        $leaveRequest = LeaveRequest::first();
        $this->assertSame(0.25, (float) $leaveRequest->day_count);
        $this->assertSame(2.0, (float) $leaveRequest->hours);
        $this->assertSame('am', $leaveRequest->half_day_period);
    }

    public function test_paid_leave_hours_requires_am_pm_period(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave', 'approver_id' => $approver->id,
            'start_date' => '2026-08-10', 'granularity' => 'hours',
        ])->assertSessionHasErrors('half_day_period');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_paid_leave_half_day_requires_am_pm_period(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave', 'approver_id' => $approver->id,
            'start_date' => '2026-08-10', 'granularity' => 'half_day',
        ])->assertSessionHasErrors('half_day_period');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_paid_leave_half_day_stores_am_pm_period(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave', 'approver_id' => $approver->id,
            'start_date' => '2026-08-10', 'granularity' => 'half_day', 'half_day_period' => 'pm',
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::first();
        $this->assertSame('pm', $leaveRequest->half_day_period);
        $this->assertSame(0.5, (float) $leaveRequest->day_count);
        $this->assertSame('PM半休', $leaveRequest->shortLabel());
    }

    /**
     * 承認者は承認画面から詳細へ遷移して判断するため、午前/午後は詳細に出ている必要がある。
     */
    public function test_detail_page_shows_am_pm_period_for_half_day_and_hours(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $make = fn (string $granularity, string $period) => LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => $granularity, 'half_day_period' => $period,
            'day_count' => 0.5, 'approver_id' => $approver->id, 'status' => 'pending',
        ]);

        $this->actingAs($approver)->get(route('leave-requests.show', $make('hours', 'pm')))
            ->assertOk()->assertSee('午前/午後')->assertSee('午後(PM)');
        $this->actingAs($approver)->get(route('leave-requests.show', $make('half_day', 'am')))
            ->assertOk()->assertSee('午前(AM)');

        // 午前/午後を持たない1日有休では、この行ごと出さない。
        $fullDay = LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave',
            'start_date' => '2026-08-11', 'end_date' => '2026-08-11',
            'granularity' => 'full_day', 'day_count' => 1.0,
            'approver_id' => $approver->id, 'status' => 'pending',
        ]);
        $this->actingAs($approver)->get(route('leave-requests.show', $fullDay))
            ->assertOk()->assertDontSee('午前/午後');
    }

    public function test_short_label_covers_paid_leave_variants(): void
    {
        $staff = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $make = fn (array $overrides) => LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'pending', ...$overrides,
        ]);

        $this->assertSame('1日有休', $make(['granularity' => 'full_day'])->shortLabel());
        $this->assertSame('AM2H休', $make(['granularity' => 'hours', 'half_day_period' => 'am'])->shortLabel());
        $this->assertSame('PM2H休', $make(['granularity' => 'hours', 'half_day_period' => 'pm'])->shortLabel());
        // AM/PM必須化より前に登録された2時間有休のフォールバック
        $this->assertSame('2H休', $make(['granularity' => 'hours'])->shortLabel());
        $this->assertSame('AM半休', $make(['granularity' => 'half_day', 'half_day_period' => 'am'])->shortLabel());
        $this->assertSame('PM半休', $make(['granularity' => 'half_day', 'half_day_period' => 'pm'])->shortLabel());

        $telework = LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'telework', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'approver_id' => $approver->id, 'status' => 'pending',
        ]);
        $this->assertSame('在宅', $telework->shortLabel());

        $holidayWork = LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'holiday_work', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'order_no' => 'A-1', 'work_location' => '本社', 'no_substitute_needed' => true,
            'approver_id' => $approver->id, 'status' => 'pending',
        ]);
        $this->assertSame('休出', $holidayWork->shortLabel());
    }

    public function test_paid_leave_request_is_rejected_when_balance_is_insufficient(): void
    {
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 0.5]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'granularity' => 'full_day',
        ])->assertSessionHasErrors('granularity');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_paid_leave_balance_consumes_last_year_grant_before_current_year(): void
    {
        $staff = Staff::factory()->create([
            'paid_leave_granted_last_year' => 3,
            'paid_leave_granted_current_year' => 10,
        ]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-07-15', 'end_date' => '2026-07-19',
            'granularity' => 'full_day', 'day_count' => 5.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $balance = $staff->paidLeaveBalance();

        $this->assertSame(0.0, $balance['remainingLastYear']);
        $this->assertSame(8.0, $balance['remainingCurrentYear']);
        $this->assertSame(8.0, $balance['remainingTotal']);
    }

    public function test_paid_leave_balance_deducts_pending_requests_but_ignores_rejected(): void
    {
        $staff = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-07-15', 'end_date' => '2026-07-15',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-07-16', 'end_date' => '2026-07-16',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_REJECTED,
        ]);

        $balance = $staff->paidLeaveBalance();

        // 承認待ちは「消化見込み」として残数から差し引く(承認前に残数を超える申請を
        // 何本も出せてしまうのを防ぐため)。却下済みは残数に影響しない。
        $this->assertSame(1.0, $balance['pending']);
        $this->assertSame(0.0, $balance['consumed']);
        $this->assertSame(9.0, $balance['remainingTotal']);
    }

    public function test_paid_leave_balance_only_counts_the_current_paid_leave_year(): void
    {
        $staff = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        // 有休年度は7/1起算(会社の付与日)。前年度(〜6/30)に消化した分は当年度の残数から差し引かない。
        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-06-30', 'end_date' => '2026-06-30',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_APPROVED,
        ]);
        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-07-01', 'end_date' => '2026-07-01',
            'granularity' => 'full_day', 'day_count' => 2.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $balance = $staff->paidLeaveBalance();

        $this->assertSame(2.0, $balance['consumed']);
        $this->assertSame(8.0, $balance['remainingTotal']);
    }

    public function test_paid_leave_year_runs_from_july_to_june(): void
    {
        [$start, $end] = Staff::paidLeaveYearPeriod(\Illuminate\Support\Carbon::parse('2026-08-10'));
        $this->assertSame('2026-07-01', $start->toDateString());
        $this->assertSame('2027-06-30', $end->toDateString());

        // 6/30時点はまだ前の年度に属する。
        [$start, $end] = Staff::paidLeaveYearPeriod(\Illuminate\Support\Carbon::parse('2026-06-30'));
        $this->assertSame('2025-07-01', $start->toDateString());
        $this->assertSame('2026-06-30', $end->toDateString());
    }

    public function test_ceremonial_leave_marriage_auto_fills_five_days(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'ceremonial_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'reason_code' => 'marriage',
        ]);

        $leaveRequest = LeaveRequest::first();
        $this->assertSame(5.0, (float) $leaveRequest->day_count);
        $this->assertSame('2026-08-14', $leaveRequest->end_date->format('Y-m-d'));
    }

    public function test_ceremonial_leave_funeral_uses_date_range_for_day_count(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'ceremonial_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'reason_code' => 'funeral',
            'reason_detail' => '父',
        ]);

        $leaveRequest = LeaveRequest::first();
        $this->assertSame(2.0, (float) $leaveRequest->day_count);
        $this->assertSame('父', $leaveRequest->reason_detail);
    }

    public function test_funeral_details_are_saved_when_provided(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'ceremonial_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
            'reason_code' => 'funeral',
            'reason_detail' => '父',
            'funeral_venue_address' => '三重県四日市市〇〇1-2-3 〇〇会館',
            'funeral_venue_phone' => '059-000-0000',
            'wake_datetime' => '2026-08-10T18:00',
            'funeral_datetime' => '2026-08-11T11:00',
            'flowers_declined' => '1',
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::first();
        $this->assertTrue($leaveRequest->isFuneral());
        $this->assertSame('三重県四日市市〇〇1-2-3 〇〇会館', $leaveRequest->funeral_venue_address);
        $this->assertSame('059-000-0000', $leaveRequest->funeral_venue_phone);
        $this->assertSame('2026-08-10 18:00', $leaveRequest->wake_datetime->format('Y-m-d H:i'));
        $this->assertSame('2026-08-11 11:00', $leaveRequest->funeral_datetime->format('Y-m-d H:i'));
        $this->assertTrue($leaveRequest->flowers_declined);
        $this->assertFalse($leaveRequest->telegram_declined);
    }

    public function test_funeral_fields_are_not_set_for_marriage_reason(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'ceremonial_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'reason_code' => 'marriage',
            // funeral専用欄が紛れ込んでも(異なる種別のfieldsetから)無視されることを確認
            'funeral_venue_address' => '誤って送られた値',
        ]);

        $leaveRequest = LeaveRequest::first();
        $this->assertFalse($leaveRequest->isFuneral());
        $this->assertNull($leaveRequest->funeral_venue_address);
    }

    public function test_holiday_work_requires_substitute_date_unless_flagged(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'holiday_work',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'order_no' => 'AB123-N01',
            'work_location' => '本社',
        ])->assertSessionHasErrors('substitute_holiday_date');

        $this->assertSame(0, LeaveRequest::count());

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'holiday_work',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'order_no' => 'AB123-N01',
            'work_location' => '本社',
            'no_substitute_needed' => '1',
        ])->assertRedirect(route('leave-requests.index'));

        $this->assertSame(1, LeaveRequest::count());
        $this->assertTrue((bool) LeaveRequest::first()->no_substitute_needed);
    }

    public function test_compensatory_leave_requires_six_hours_worked(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'compensatory_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'order_no' => 'AB123-N01',
            'work_location' => '本社',
            'actual_worked_hours' => '5',
            'compensatory_date' => '2026-08-15',
        ])->assertSessionHasErrors('compensatory_date');

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'compensatory_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
            'order_no' => 'AB123-N01',
            'work_location' => '本社',
            'actual_worked_hours' => '6',
            'compensatory_date' => '2026-08-15',
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::first();
        $this->assertSame('2026-08-15', $leaveRequest->compensatory_date->format('Y-m-d'));
    }

    public function test_approver_can_approve_pending_request(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->createPendingPaidLeave($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), [
            'action' => 'approve',
        ])->assertRedirect(route('leave-requests.approvals'));

        $this->assertSame('approved', $leaveRequest->fresh()->status);
        $this->assertNotNull($leaveRequest->fresh()->approved_at);
    }

    public function test_approver_can_reject_with_reason(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->createPendingPaidLeave($applicant, $approver);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), [
            'action' => 'reject',
            'rejection_reason' => '繁忙期のため',
        ])->assertRedirect(route('leave-requests.approvals'));

        $leaveRequest->refresh();
        $this->assertSame('rejected', $leaveRequest->status);
        $this->assertSame('繁忙期のため', $leaveRequest->rejection_reason);
    }

    public function test_non_approver_cannot_decide(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $stranger = Staff::factory()->create();
        $leaveRequest = $this->createPendingPaidLeave($applicant, $approver);

        $this->actingAs($stranger)->put(route('leave-requests.decide', $leaveRequest), [
            'action' => 'approve',
        ])->assertForbidden();

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_applicant_can_withdraw_pending_request(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->createPendingPaidLeave($applicant, $approver);

        $this->actingAs($applicant)->delete(route('leave-requests.withdraw', $leaveRequest))
            ->assertRedirect(route('leave-requests.index'));

        $this->assertSame('withdrawn', $leaveRequest->fresh()->status);
    }

    public function test_stranger_cannot_withdraw_others_request(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $stranger = Staff::factory()->create();
        $leaveRequest = $this->createPendingPaidLeave($applicant, $approver);

        $this->actingAs($stranger)->delete(route('leave-requests.withdraw', $leaveRequest))
            ->assertForbidden();
    }

    public function test_non_supervisor_cannot_select_self_as_approver(): void
    {
        $applicant = Staff::factory()->create();

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave',
            'approver_id' => $applicant->id,
            'start_date' => '2026-08-10',
            'granularity' => 'full_day',
        ])->assertSessionHasErrors('approver_id');

        $this->assertSame(0, LeaveRequest::count());
    }

    public function test_supervisor_can_select_self_as_approver(): void
    {
        $applicant = Staff::factory()->create([
            'is_supervisor' => true,
            'paid_leave_granted_current_year' => 10,
        ]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave',
            'approver_id' => $applicant->id,
            'start_date' => '2026-08-10',
            'granularity' => 'full_day',
        ])->assertRedirect(route('leave-requests.index'));

        $this->assertSame($applicant->id, LeaveRequest::first()->approver_id);
    }

    public function test_supervisor_is_listed_as_approver_candidate_for_own_request(): void
    {
        $applicant = Staff::factory()->create(['is_supervisor' => true, 'name' => '上長本人']);

        $this->actingAs($applicant)->get(route('leave-requests.create'))
            ->assertOk()
            ->assertSee('上長本人（自分）');
    }

    public function test_supervisor_can_approve_own_request(): void
    {
        $applicant = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->createPendingPaidLeave($applicant, $applicant);

        $this->actingAs($applicant)->put(route('leave-requests.decide', $leaveRequest), [
            'action' => 'approve',
        ])->assertRedirect(route('leave-requests.approvals'));

        $this->assertSame('approved', $leaveRequest->fresh()->status);
    }

    public function test_own_pending_request_appears_in_approvals_list(): void
    {
        $applicant = Staff::factory()->create(['is_supervisor' => true]);
        $this->createPendingPaidLeave($applicant, $applicant);

        $this->actingAs($applicant)->get(route('leave-requests.approvals'))
            ->assertOk()
            ->assertSee($applicant->name);
    }

    /**
     * 休日勤務は上長が承認しても承認済みにはならず、勤怠管理者の確認待ちになる
     * (上長が自分の申請を承認する場合も同じ)。
     */
    public function test_holiday_work_request_self_approved_by_supervisor_waits_for_the_attendance_manager(): void
    {
        $applicant = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'holiday_work',
            'approver_id' => $applicant->id,
            'start_date' => '2026-08-10',
            'order_no' => 'A-1',
            'work_location' => '本社',
            'no_substitute_needed' => true,
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::first();

        $this->actingAs($applicant)->put(route('leave-requests.decide', $leaveRequest), [
            'action' => 'approve',
        ])->assertRedirect(route('leave-requests.approvals'));

        $leaveRequest->refresh();
        $this->assertSame(LeaveRequest::STATUS_PENDING_ATTENDANCE, $leaveRequest->status);
        $this->assertNotNull($leaveRequest->supervisor_approved_at);
    }

    /**
     * 勤務日の向きが制度と食い違う場合は注意喚起する。ただし実運用では逆順の
     * 申請も起こりうるため、登録そのものは止めない。
     */
    public function test_a_past_holiday_work_request_is_warned_but_still_accepted(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'holiday_work',
            'approver_id' => $approver->id,
            'start_date' => now()->subWeek()->format('Y/m/d'),
            'order_no' => 'A-1',
            'work_location' => '本社',
            'no_substitute_needed' => true,
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::sole();
        $this->assertStringContainsString('勤務日が過去の日付です', $leaveRequest->dateWarning());

        // 申請者にも承認者にも見える。
        $this->actingAs($applicant)->get(route('leave-requests.show', $leaveRequest))
            ->assertOk()->assertSee('代休申請の方が合っている', false);
        $this->actingAs($approver)->get(route('leave-requests.approvals'))
            ->assertOk()->assertSee('要確認');
    }

    public function test_a_future_compensatory_leave_request_is_warned_but_still_accepted(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'compensatory_leave',
            'approver_id' => $approver->id,
            'start_date' => now()->addWeek()->format('Y/m/d'),
            'order_no' => 'A-1',
            'work_location' => '本社',
            'actual_worked_hours' => 8,
            'compensatory_date' => now()->addDays(10)->format('Y/m/d'),
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::sole();
        $this->assertStringContainsString('勤務した日が未来の日付です', $leaveRequest->dateWarning());
        $this->assertStringContainsString('休日勤務申請の方が合っている', $leaveRequest->dateWarning());
    }

    public function test_dates_in_the_expected_direction_are_not_warned(): void
    {
        $staff = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $make = fn (string $type, string $date) => LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => $type, 'approver_id' => $approver->id,
            'start_date' => $date, 'end_date' => $date, 'status' => 'pending',
        ]);

        // 休日勤務は今日以降、代休は今日以前が想定どおり。当日はどちらも警告しない。
        $this->assertNull($make('holiday_work', now()->addWeek()->toDateString())->dateWarning());
        $this->assertNull($make('holiday_work', now()->toDateString())->dateWarning());
        $this->assertNull($make('compensatory_leave', now()->subWeek()->toDateString())->dateWarning());
        $this->assertNull($make('compensatory_leave', now()->toDateString())->dateWarning());

        // 他の種別は日付の向きを問わない。
        $this->assertNull($make('paid_leave', now()->subMonth()->toDateString())->dateWarning());
        $this->assertNull($make('telework', now()->addMonth()->toDateString())->dateWarning());
    }

    public function test_a_supervisor_can_approve_several_requests_at_once(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $requests = collect(range(1, 3))->map(
            fn () => $this->createPendingPaidLeave(Staff::factory()->create(), $approver)
        );

        $this->actingAs($approver)->post(route('leave-requests.bulk-approve'), [
            'ids' => $requests->pluck('id')->all(),
        ])->assertRedirect(route('leave-requests.approvals'))
            ->assertSessionHas('bulkApprovedCount', 3);

        foreach ($requests as $leaveRequest) {
            $this->assertSame('approved', $leaveRequest->fresh()->status);
            $this->assertNotNull($leaveRequest->fresh()->approved_at);
        }

        // 1件ずつの承認と同じように操作ログを残す。
        $this->assertSame(3, OperationLog::where('action', OperationLog::ACTION_LEAVE_REQUEST_APPROVE)->count());
    }

    public function test_bulk_approval_ignores_requests_the_supervisor_does_not_approve(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $otherApprover = Staff::factory()->create(['is_supervisor' => true]);

        $mine = $this->createPendingPaidLeave(Staff::factory()->create(), $approver);
        $someoneElses = $this->createPendingPaidLeave(Staff::factory()->create(), $otherApprover);

        $this->actingAs($approver)->post(route('leave-requests.bulk-approve'), [
            'ids' => [$mine->id, $someoneElses->id],
        ])->assertRedirect(route('leave-requests.approvals'));

        $this->assertSame('approved', $mine->fresh()->status);
        $this->assertSame('pending', $someoneElses->fresh()->status, '他人が承認者の申請は触らない');
    }

    public function test_bulk_approval_skips_requests_that_are_no_longer_pending(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $pending = $this->createPendingPaidLeave(Staff::factory()->create(), $approver);
        $withdrawn = $this->createPendingPaidLeave(Staff::factory()->create(), $approver);
        $withdrawn->update(['status' => LeaveRequest::STATUS_WITHDRAWN]);

        $this->actingAs($approver)->post(route('leave-requests.bulk-approve'), [
            'ids' => [$pending->id, $withdrawn->id],
        ])->assertRedirect(route('leave-requests.approvals'))
            ->assertSessionHas('bulkApprovedCount', 1);

        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertSame('withdrawn', $withdrawn->fresh()->status);
    }

    public function test_bulk_approval_reports_when_nothing_could_be_approved(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $alreadyDone = $this->createPendingPaidLeave(Staff::factory()->create(), $approver);
        $alreadyDone->update(['status' => LeaveRequest::STATUS_APPROVED]);

        $this->actingAs($approver)->post(route('leave-requests.bulk-approve'), [
            'ids' => [$alreadyDone->id],
        ])->assertSessionHasErrors('ids');
    }

    public function test_bulk_approval_needs_at_least_one_selection(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($approver)->post(route('leave-requests.bulk-approve'), [])
            ->assertSessionHasErrors('ids');
    }

    public function test_bulk_approval_is_limited_to_supervisors_and_managers(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = $this->createPendingPaidLeave(Staff::factory()->create(), $approver);

        $this->actingAs(Staff::factory()->create())
            ->post(route('leave-requests.bulk-approve'), ['ids' => [$leaveRequest->id]])
            ->assertForbidden();

        $this->assertSame('pending', $leaveRequest->fresh()->status);
    }

    public function test_the_approvals_screen_offers_bulk_approval(): void
    {
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $this->createPendingPaidLeave(Staff::factory()->create(), $approver);

        $this->actingAs($approver)->get(route('leave-requests.approvals'))
            ->assertOk()
            ->assertSee('すべて選択')
            ->assertSee('選択した申請を承認');
    }

    private function createPendingPaidLeave(Staff $applicant, Staff $approver): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => $applicant->id,
            'type' => 'paid_leave',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'granularity' => 'full_day',
            'day_count' => 1.0,
            'approver_id' => $approver->id,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }
}
