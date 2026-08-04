<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\LeaveRequest;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_daily_report_records_a_submit_log(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-10',
            'entries' => [],
            'submit' => '1',
        ])->assertRedirect();

        $log = OperationLog::sole();
        $this->assertSame(OperationLog::ACTION_DAILY_REPORT_SUBMIT, $log->action);
        $this->assertSame($staff->id, $log->staff_id);
        $this->assertSame($staff->id, $log->owner_staff_id);
        $this->assertSame(DailyReport::class, $log->subject_type);
    }

    public function test_resubmitting_a_daily_report_records_a_resubmit_log(): void
    {
        $staff = Staff::factory()->create();
        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-10',
            'entries' => [],
            'submit' => '1',
        ])->assertRedirect();

        $log = OperationLog::sole();
        $this->assertSame(OperationLog::ACTION_DAILY_REPORT_RESUBMIT, $log->action);
    }

    public function test_saving_a_draft_does_not_record_a_log(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-10',
            'entries' => [],
        ])->assertRedirect();

        $this->assertSame(0, OperationLog::count());
    }

    public function test_confirming_a_report_records_a_confirm_log_owned_by_the_reporter(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $reporter = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);

        $this->actingAs($manager)->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertRedirect();

        $log = OperationLog::sole();
        $this->assertSame(OperationLog::ACTION_DAILY_REPORT_CONFIRM, $log->action);
        $this->assertSame($manager->id, $log->staff_id);
        $this->assertSame($reporter->id, $log->owner_staff_id);
    }

    public function test_rejecting_a_report_records_the_reason_in_the_log(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $reporter = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);

        $this->actingAs($manager)->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '注番が間違っています',
        ])->assertRedirect();

        $log = OperationLog::sole();
        $this->assertSame(OperationLog::ACTION_DAILY_REPORT_REJECT, $log->action);
        $this->assertSame('注番が間違っています', $log->description);
    }

    public function test_creating_a_leave_request_records_a_log(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'telework',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-10',
        ])->assertRedirect();

        $log = OperationLog::sole();
        $this->assertSame(OperationLog::ACTION_LEAVE_REQUEST_CREATE, $log->action);
        $this->assertSame($applicant->id, $log->owner_staff_id);
        $this->assertSame(LeaveRequest::class, $log->subject_type);
    }

    public function test_withdrawing_a_leave_request_records_a_log(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'pending',
        ]);

        $this->actingAs($applicant)->delete(route('leave-requests.withdraw', $leaveRequest))->assertRedirect();

        $log = OperationLog::sole();
        $this->assertSame(OperationLog::ACTION_LEAVE_REQUEST_WITHDRAW, $log->action);
    }

    public function test_approving_and_rejecting_a_leave_request_records_logs(): void
    {
        $applicant = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);
        $leaveRequest = LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'pending',
        ]);

        $this->actingAs($approver)->put(route('leave-requests.decide', $leaveRequest), ['action' => 'approve'])
            ->assertRedirect();

        $log = OperationLog::sole();
        $this->assertSame(OperationLog::ACTION_LEAVE_REQUEST_APPROVE, $log->action);
        $this->assertSame($approver->id, $log->staff_id);
        $this->assertSame($applicant->id, $log->owner_staff_id);
    }

    public function test_privileged_viewer_sees_all_logs(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staffA = Staff::factory()->create(['name' => '担当者A']);
        $staffB = Staff::factory()->create(['name' => '担当者B']);

        OperationLog::create(['staff_id' => $staffA->id, 'owner_staff_id' => $staffA->id, 'action' => OperationLog::ACTION_LEAVE_REQUEST_CREATE]);
        OperationLog::create(['staff_id' => $staffB->id, 'owner_staff_id' => $staffB->id, 'action' => OperationLog::ACTION_LEAVE_REQUEST_CREATE]);

        $response = $this->actingAs($manager)->get(route('operation-logs.index'));

        $response->assertOk();
        $response->assertSee('担当者A');
        $response->assertSee('担当者B');
    }

    public function test_general_staff_sees_only_their_own_logs(): void
    {
        $staffA = Staff::factory()->create(['name' => '担当者A']);
        $staffB = Staff::factory()->create(['name' => '担当者B']);

        OperationLog::create(['staff_id' => $staffA->id, 'owner_staff_id' => $staffA->id, 'action' => OperationLog::ACTION_LEAVE_REQUEST_CREATE]);
        OperationLog::create(['staff_id' => $staffB->id, 'owner_staff_id' => $staffB->id, 'action' => OperationLog::ACTION_LEAVE_REQUEST_CREATE]);

        $response = $this->actingAs($staffA)->get(route('operation-logs.index'));

        $response->assertOk();
        $response->assertDontSee('担当者B');
    }

    public function test_prune_command_deletes_only_logs_older_than_five_years(): void
    {
        $staff = Staff::factory()->create();

        $old = OperationLog::create(['staff_id' => $staff->id, 'owner_staff_id' => $staff->id, 'action' => OperationLog::ACTION_LEAVE_REQUEST_CREATE]);
        $old->timestamps = false;
        $old->created_at = now()->subYears(5)->subDay();
        $old->save();

        $recent = OperationLog::create(['staff_id' => $staff->id, 'owner_staff_id' => $staff->id, 'action' => OperationLog::ACTION_LEAVE_REQUEST_CREATE]);

        $this->artisan('app:prune-operation-logs');

        $this->assertModelMissing($old);
        $this->assertNotNull($recent->fresh());
    }
}
