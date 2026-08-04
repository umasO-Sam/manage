<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\LaborCost;
use App\Models\Staff;
use App\Services\WorkTimeComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkTimeComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkTimeComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkTimeComplianceService::class);
    }

    public function test_week_period_starts_on_saturday(): void
    {
        // 2026-08-10は月曜日
        [$start, $end] = $this->service->weekPeriod(Carbon::parse('2026-08-10'));

        $this->assertSame('2026-08-08', $start->format('Y-m-d')); // 直前の土曜日
        $this->assertSame('2026-08-14', $end->format('Y-m-d'));
    }

    public function test_month_period_uses_20th_cutoff(): void
    {
        [$start, $end] = $this->service->monthPeriod(Carbon::parse('2026-08-20'));
        $this->assertSame('2026-07-21', $start->format('Y-m-d'));
        $this->assertSame('2026-08-20', $end->format('Y-m-d'));

        [$start2, $end2] = $this->service->monthPeriod(Carbon::parse('2026-08-21'));
        $this->assertSame('2026-08-21', $start2->format('Y-m-d'));
        $this->assertSame('2026-09-20', $end2->format('Y-m-d'));
    }

    public function test_fiscal_year_period_starts_april_21(): void
    {
        [$start, $end] = $this->service->fiscalYearPeriod(Carbon::parse('2026-04-20'));
        $this->assertSame('2025-04-21', $start->format('Y-m-d'));
        $this->assertSame('2026-04-20', $end->format('Y-m-d'));

        [$start2, $end2] = $this->service->fiscalYearPeriod(Carbon::parse('2026-04-21'));
        $this->assertSame('2026-04-21', $start2->format('Y-m-d'));
        $this->assertSame('2027-04-20', $end2->format('Y-m-d'));
    }

    public function test_overtime_counts_full_worked_time_on_rest_days(): void
    {
        $staff = Staff::factory()->create();

        // 平日(木曜)に9時間 -> 残業1時間
        LaborCost::create(['work_date' => '2026-08-06', 'staff_id' => $staff->id, 'work_hours' => 9, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        // 土曜(休日)に4時間 -> 全て残業扱い
        LaborCost::create(['work_date' => '2026-08-08', 'staff_id' => $staff->id, 'work_hours' => 4, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);

        $overtime = $this->service->overtimeMinutesForPeriod($staff, Carbon::parse('2026-08-06'), Carbon::parse('2026-08-08'));

        $this->assertSame(60 + 240, $overtime);
    }

    public function test_overtime_counts_full_worked_time_on_company_holidays(): void
    {
        $staff = Staff::factory()->create();
        Holiday::create(['date' => '2026-08-06', 'name' => '会社休日', 'type' => Holiday::TYPE_COMPANY_HOLIDAY]);

        LaborCost::create(['work_date' => '2026-08-06', 'staff_id' => $staff->id, 'work_hours' => 3, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);

        $overtime = $this->service->overtimeMinutesForPeriod($staff, Carbon::parse('2026-08-06'), Carbon::parse('2026-08-06'));

        $this->assertSame(180, $overtime);
    }

    public function test_special_clause_summary_flags_hard_cap_and_counts_months_over_45_hours(): void
    {
        $staff = Staff::factory()->create();

        // 当月(20日締め、2026-07-21〜2026-08-20)に平日8日で1日14時間ずつ働かせ、
        // 残業が(14-8)*8=48時間(45時間超)になるようにする。100時間到達は別ケースでテストする。
        foreach (['2026-07-27', '2026-07-28', '2026-07-29', '2026-07-30', '2026-07-31', '2026-08-03', '2026-08-04', '2026-08-05'] as $date) {
            LaborCost::create(['work_date' => $date, 'staff_id' => $staff->id, 'work_hours' => 14, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        }

        $summary = $this->service->specialClauseSummary($staff, Carbon::parse('2026-08-05'));

        $this->assertSame(8 * 6 * 60, $summary['monthOvertimeMinutes']);
        $this->assertFalse($summary['hardCapExceeded']);
        $this->assertSame(1, $summary['specialClauseMonthsUsedThisFiscalYear']);
        $this->assertSame(5, $summary['specialClauseMonthsRemaining']);
    }

    public function test_special_clause_summary_flags_hard_cap_when_reaching_100_hours(): void
    {
        $staff = Staff::factory()->create();

        // 平日10日、1日18時間 -> 残業(18-8)*10=100時間ちょうど
        $dates = ['2026-07-21', '2026-07-22', '2026-07-23', '2026-07-24', '2026-07-27', '2026-07-28', '2026-07-29', '2026-07-30', '2026-07-31', '2026-08-03'];
        foreach ($dates as $date) {
            LaborCost::create(['work_date' => $date, 'staff_id' => $staff->id, 'work_hours' => 18, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        }

        $summary = $this->service->specialClauseSummary($staff, Carbon::parse('2026-08-03'));

        $this->assertSame(100 * 60, $summary['monthOvertimeMinutes']);
        $this->assertTrue($summary['hardCapExceeded']);
        $this->assertSame(0, $summary['hardCapRemainingMinutes']);
    }
}
