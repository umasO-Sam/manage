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

    public function test_shows_35_days_from_one_week_ago_to_four_weeks_ahead(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('dates', function (array $dates) {
            return $dates[0] === '2026-08-03' && end($dates) === '2026-09-06' && count($dates) === 35;
        });
    }

    public function test_general_staff_sees_only_their_own_row(): void
    {
        $staff = Staff::factory()->create(['name' => '自分太郎']);
        $other = Staff::factory()->create(['name' => '他人次郎']);

        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        DailyReport::create(['staff_id' => $other->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);

        $response = $this->actingAs($staff)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertSee('自分太郎');
        $response->assertDontSee('他人次郎');
    }

    public function test_privileged_viewer_sees_all_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staffA = Staff::factory()->create(['name' => '担当者A']);
        $staffB = Staff::factory()->create(['name' => '担当者B']);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertSee('担当者A');
        $response->assertSee('担当者B');
    }

    public function test_shows_pending_confirmation_status(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertSee('確認待ち');
    }

    public function test_review_link_only_shown_to_procurement_managers(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->get(route('daily-reports.list.index'))
            ->assertSee(route('daily-reports.review.index', ['date' => '2026-08-10']), false);

        $this->actingAs($supervisor)->get(route('daily-reports.list.index'))
            ->assertDontSee('daily-reports/review');
    }

    public function test_staff_are_grouped_by_department(): void
    {
        $manager = Staff::factory()->procurementManager()->create(['department' => '役員']);
        Staff::factory()->create(['name' => '製造太郎', 'department' => '製造', 'display_order' => 1]);
        Staff::factory()->create(['name' => '営業花子', 'department' => '営業', 'display_order' => 1]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $content = $response->getContent();
        $this->assertLessThan(strpos($content, '製造太郎'), strpos($content, '営業花子'));
    }
}
