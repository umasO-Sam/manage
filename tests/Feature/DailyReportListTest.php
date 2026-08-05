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

    public function test_procurement_manager_can_view_the_page(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('daily-reports.list.index'))->assertOk();
    }

    public function test_supervisor_can_view_the_page(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($supervisor)->get(route('daily-reports.list.index'))->assertOk();
    }

    public function test_general_staff_cannot_access_the_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('daily-reports.list.index'))->assertForbidden();
    }

    public function test_shows_the_past_three_weeks_ending_today(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('dates', function (array $dates) {
            return $dates[0] === '2026-07-21' && end($dates) === '2026-08-10' && count($dates) === 21;
        });
    }

    public function test_shows_36_agreement_indicators_per_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create(['name' => '残業太郎']);

        // 当月(7/21〜8/20)に単月100時間超の残業を作る: 平日8時間+8時間を14日分。
        for ($i = 0; $i < 14; $i++) {
            $date = \Illuminate\Support\Carbon::parse('2026-07-21')->addDays($i);
            if (in_array($date->dayOfWeek, [0, 6], true)) {
                continue;
            }
            LaborCost::create([
                'work_date' => $date->format('Y-m-d'), 'staff_id' => $staff->id,
                'work_hours' => 20, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('complianceByStaff', function (array $map) use ($staff) {
            return ($map[$staff->id]['hardCapExceeded'] ?? false) === true
                && ($map[$staff->id]['level'] ?? null) === 'danger';
        });
        $response->assertSee('危険');
    }

    public function test_shows_blue_marker_for_labor_costs_registered_from_purchase_input(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create(['name' => '入力済太郎']);

        // 仕入管理のデータ入力で登録されたレコード(作業日報を経由していない)。
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('purchaseInputByStaffAndDate', function (array $map) use ($staff) {
            return ($map[$staff->id]['2026-08-05'] ?? false) === true;
        });
        $response->assertSee('入力済み（仕入管理データ入力）');
    }

    public function test_does_not_mark_daily_report_generated_labor_costs_as_purchase_input(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);

        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('purchaseInputByStaffAndDate', fn (array $map) => $map === []);
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

    public function test_review_link_shown_only_to_managers(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->get(route('daily-reports.list.index'))
            ->assertSee(route('daily-reports.review.index', ['date' => '2026-08-10']), false);

        // 作業日報確認は資材管理担当者の業務のため、上長には導線を出さない。
        $this->actingAs($supervisor)->get(route('daily-reports.list.index'))
            ->assertDontSee(route('daily-reports.review.index', ['date' => '2026-08-10']), false);
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
