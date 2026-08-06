<?php

namespace Tests\Feature;

use App\Http\Controllers\DailyReportReviewController;
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

    public function test_review_list_shows_the_reports_of_the_requested_date_with_their_status(): void
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

        // 同日だが確認済みのため対象外(1担当者1日1件の制約があるため別担当者にする)
        $otherReporter = Staff::factory()->create();
        $confirmedReport = DailyReport::create(['staff_id' => $otherReporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-10', 'staff_id' => $otherReporter->id, 'daily_report_id' => $confirmedReport->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false,
        ]);

        // 別日のため対象外
        $otherDateReport = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-11', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-11', 'staff_id' => $reporter->id, 'daily_report_id' => $otherDateReport->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.review.index', ['date' => '2026-08-10']));

        $response->assertOk();
        // 同じ日の確認済みも状態が読めるよう残し、別日のものだけを除く。
        $response->assertViewHas('reports', function ($reports) use ($report, $confirmedReport, $otherDateReport) {
            $ids = $reports->pluck('id')->all();

            return in_array($report->id, $ids, true)
                && in_array($confirmedReport->id, $ids, true)
                && ! in_array($otherDateReport->id, $ids, true);
        });
        $response->assertViewHas('statuses', fn ($statuses) => $statuses[$report->id] === DailyReportReviewController::STATUS_PENDING
            && $statuses[$confirmedReport->id] === DailyReportReviewController::STATUS_CONFIRMED);
        $response->assertSee('雑作業');
    }

    public function test_default_date_is_the_earliest_pending_report(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $reporter = Staff::factory()->create();

        $later = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-15', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-15', 'staff_id' => $reporter->id, 'daily_report_id' => $later->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);
        $earlier = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $reporter->id, 'daily_report_id' => $earlier->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.review.index'));

        $response->assertViewHas('date', '2026-08-05');
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

        $this->actingAs($manager)->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertRedirect();

        $this->assertFalse((bool) $laborCost->fresh()->is_provisional);
    }

    public function test_rejecting_a_report_requires_a_reason(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $reporter = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);

        $this->actingAs($manager)->post(route('daily-reports.review.decide', $report), ['action' => 'reject'])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertFalse($report->fresh()->isRejected());
    }

    public function test_rejecting_a_report_records_the_reason_and_marks_it_as_rejected(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $reporter = Staff::factory()->create();

        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-10', 'staff_id' => $reporter->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $this->actingAs($manager)->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '注番が間違っています',
        ])->assertRedirect();

        $fresh = $report->fresh();
        $this->assertTrue($fresh->isRejected());
        $this->assertSame('注番が間違っています', $fresh->rejection_reason);

        // 差し戻し中も一覧には残し、状態として読み取れるようにする。
        $response = $this->actingAs($manager)->get(route('daily-reports.review.index', ['date' => '2026-08-10']));
        $response->assertViewHas('statuses', fn ($statuses) => $statuses[$report->id] === DailyReportReviewController::STATUS_REJECTED);
    }

    public function test_resubmitting_a_rejected_report_clears_the_rejection(): void
    {
        $staff = Staff::factory()->create();
        $report = DailyReport::create([
            'staff_id' => $staff->id, 'work_date' => '2026-08-10', 'submitted_at' => now(),
            'rejected_at' => now(), 'rejection_reason' => '注番が間違っています',
        ]);

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-10',
            'entries' => [],
            'submit' => '1',
        ])->assertRedirect();

        $fresh = $report->fresh();
        $this->assertFalse($fresh->isRejected());
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_general_staff_cannot_confirm_a_report(): void
    {
        $staff = Staff::factory()->create();
        $reporter = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $reporter->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);

        $this->actingAs($staff)->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertForbidden();
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
