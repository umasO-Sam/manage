<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyReportListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_any_staff_can_view_the_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('daily-reports.list.index'))->assertOk();
    }

    public function test_general_staff_sees_only_their_own_reports(): void
    {
        $staff = Staff::factory()->create(['name' => '自分太郎']);
        $other = Staff::factory()->create(['name' => '他人次郎']);

        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        DailyReport::create(['staff_id' => $other->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);

        $response = $this->actingAs($staff)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertSee('2026/08/05');
        $response->assertDontSee('他人次郎');
    }

    public function test_privileged_viewer_sees_all_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staffA = Staff::factory()->create(['name' => '担当者A']);
        $staffB = Staff::factory()->create(['name' => '担当者B']);

        DailyReport::create(['staff_id' => $staffA->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        DailyReport::create(['staff_id' => $staffB->id, 'work_date' => '2026-08-06', 'submitted_at' => now()]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertSee('担当者A');
        $response->assertSee('担当者B');
    }

    public function test_privileged_viewer_can_filter_by_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staffA = Staff::factory()->create(['name' => '担当者A']);
        $staffB = Staff::factory()->create(['name' => '担当者B']);

        DailyReport::create(['staff_id' => $staffA->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        DailyReport::create(['staff_id' => $staffB->id, 'work_date' => '2026-08-06', 'submitted_at' => now()]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index', ['staff_id' => $staffA->id]));

        $response->assertSee('2026/08/05');
        $response->assertDontSee('2026/08/06');
    }

    public function test_status_filter_narrows_results(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        $draft = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-01']);
        $rejected = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-02', 'submitted_at' => now(), 'rejected_at' => now(), 'rejection_reason' => '差戻し理由テスト']);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index', ['status' => 'rejected']));

        $response->assertSee('差戻し理由テスト');
        $response->assertDontSee('2026/08/01');
    }

    public function test_date_range_filter_excludes_reports_outside_range(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-25', 'submitted_at' => now()]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index', [
            'start_date' => '2026-08-01', 'end_date' => '2026-08-10',
        ]));

        $response->assertSee('2026/08/05');
        $response->assertDontSee('2026/08/25');
    }

    public function test_pending_report_shows_review_link_for_procurement_manager(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertSee(route('daily-reports.review.index', ['date' => '2026-08-05']), false);
        $response->assertSee('確認待ち');
    }

    public function test_own_report_shows_edit_link(): void
    {
        $staff = Staff::factory()->create();
        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05']);

        $response = $this->actingAs($staff)->get(route('daily-reports.list.index'));

        $response->assertSee(route('daily-reports.show', ['date' => '2026-08-05']), false);
        $response->assertSee('下書き');
    }
}
