<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_staff_can_view_the_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('work-status.index'))->assertOk();
    }

    public function test_general_staff_sees_neutral_badges_without_approval_status(): void
    {
        $staff = Staff::factory()->create();
        $applicant = Staff::factory()->create(['name' => '申請太郎']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'pending',
        ]);

        $response = $this->actingAs($staff)->get(route('work-status.index', ['date' => '2026-08-10']));

        $response->assertOk();
        // 「作業日報」列(見出し)は一般社員には表示されない。ナビゲーションの
        // 「作業日報」リンク自体は誰でも見えるため、日報ステータス特有の文言で判定する。
        $response->assertDontSee('確認待ち');
        $response->assertSee('有給休暇');
    }

    public function test_supervisor_sees_daily_report_status_and_colored_badges(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);
        $applicant = Staff::factory()->create(['name' => '申請太郎']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'approved',
        ]);

        $report = DailyReport::create(['staff_id' => $applicant->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-10', 'staff_id' => $applicant->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $response = $this->actingAs($supervisor)->get(route('work-status.index', ['date' => '2026-08-10']));

        $response->assertOk();
        $response->assertSee('作業日報');
        $response->assertSee('確認待ち');
    }

    public function test_review_button_only_shown_to_procurement_managers(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->get(route('work-status.index'))
            ->assertSee('この日の作業日報を確認する');

        $this->actingAs($supervisor)->get(route('work-status.index'))
            ->assertDontSee('この日の作業日報を確認する');
    }
}
