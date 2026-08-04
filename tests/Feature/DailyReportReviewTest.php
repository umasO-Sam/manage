<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\DailyReportEntry;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_procurement_managers_can_view_the_review_list(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('daily-reports.review.index'))->assertForbidden();
    }

    public function test_review_list_shows_reports_with_provisional_labor_costs(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $reporter = Staff::factory()->create();

        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);
        DailyReportEntry::create([
            'daily_report_id' => $report->id, 'start_minute' => 480, 'end_minute' => 600,
            'order_no' => 'A-1', 'category_id' => null, 'is_other' => true, 'free_text' => '雑作業',
        ]);
        LaborCost::create([
            'work_date' => '2026-08-10', 'staff_id' => $reporter->id, 'daily_report_id' => $report->id,
            'work_hours' => 2, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $confirmedReport = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-11', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-11', 'staff_id' => $reporter->id, 'daily_report_id' => $confirmedReport->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.review.index'));

        $response->assertOk();
        $response->assertViewHas('reports', function ($reports) use ($report, $confirmedReport) {
            $ids = $reports->pluck('id')->all();

            return in_array($report->id, $ids, true) && ! in_array($confirmedReport->id, $ids, true);
        });
        $response->assertSee('雑作業');
    }

    public function test_confirming_a_report_clears_provisional_flag(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $reporter = Staff::factory()->create();

        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);
        $laborCost = LaborCost::create([
            'work_date' => '2026-08-10', 'staff_id' => $reporter->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $this->actingAs($manager)->post(route('daily-reports.review.confirm', $report))
            ->assertRedirect();

        $this->assertFalse((bool) $laborCost->fresh()->is_provisional);
    }

    public function test_general_staff_cannot_confirm_a_report(): void
    {
        $staff = Staff::factory()->create();
        $reporter = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);

        $this->actingAs($staff)->post(route('daily-reports.review.confirm', $report))->assertForbidden();
    }

    public function test_pending_approvals_count_only_counts_own_pending_requests(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);
        $otherSupervisor = Staff::factory()->create(['is_supervisor' => true]);
        $applicant = Staff::factory()->create();

        \App\Models\LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $supervisor->id, 'status' => 'pending',
        ]);
        \App\Models\LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-11', 'end_date' => '2026-08-11',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $otherSupervisor->id, 'status' => 'pending',
        ]);
        \App\Models\LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $supervisor->id, 'status' => 'approved',
        ]);

        $this->assertSame(1, $supervisor->pendingApprovalsCount());
    }
}
