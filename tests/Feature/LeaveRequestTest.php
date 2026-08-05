<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
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
        ]);

        $leaveRequest = LeaveRequest::first();
        $this->assertSame(0.25, (float) $leaveRequest->day_count);
        $this->assertSame(2.0, (float) $leaveRequest->hours);
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

    public function test_short_label_covers_paid_leave_variants(): void
    {
        $staff = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $make = fn (array $overrides) => LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'pending', ...$overrides,
        ]);

        $this->assertSame('1日有休', $make(['granularity' => 'full_day'])->shortLabel());
        $this->assertSame('2H有休', $make(['granularity' => 'hours'])->shortLabel());
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
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-04-01', 'end_date' => '2026-04-05',
            'granularity' => 'full_day', 'day_count' => 5.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $balance = $staff->paidLeaveBalance();

        $this->assertSame(0.0, $balance['remainingLastYear']);
        $this->assertSame(8.0, $balance['remainingCurrentYear']);
        $this->assertSame(8.0, $balance['remainingTotal']);
    }

    public function test_paid_leave_balance_ignores_pending_and_rejected_requests(): void
    {
        $staff = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-04-01', 'end_date' => '2026-04-01',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-04-02', 'end_date' => '2026-04-02',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_REJECTED,
        ]);

        $this->assertSame(10.0, $staff->paidLeaveBalance()['remainingTotal']);
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

    public function test_cannot_select_self_as_approver(): void
    {
        $applicant = Staff::factory()->create();

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave',
            'approver_id' => $applicant->id,
            'start_date' => '2026-08-10',
            'granularity' => 'full_day',
        ])->assertSessionHasErrors('approver_id');
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
