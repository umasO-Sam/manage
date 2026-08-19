<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Services\TimecardService;
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

    public function test_marks_labor_costs_registered_from_purchase_input(): void
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

    /**
     * 終日休みの日は作業日報が要らないため、提出漏れと区別できるようグレーで示す。
     */
    public function test_full_day_leave_is_marked_as_no_report_needed(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        $this->approvedLeave($staff, ['type' => 'paid_leave', 'granularity' => 'full_day', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03']);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('fullDayOffByStaffAndDate', function (array $map) use ($staff) {
            return ($map[$staff->id]['2026-08-03'] ?? false) === true;
        });
        $response->assertSee('終日休み（作業日報は不要）');
    }

    /**
     * 半休・2時間有休は残りの時間を勤務するため、日報の提出が必要。
     */
    public function test_half_day_and_two_hour_leave_are_not_marked_as_no_report_needed(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        $this->approvedLeave($staff, ['type' => 'paid_leave', 'granularity' => 'half_day', 'half_day_period' => 'am', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03']);
        $this->approvedLeave($staff, ['type' => 'paid_leave', 'granularity' => 'hours', 'half_day_period' => 'pm', 'start_date' => '2026-08-04', 'end_date' => '2026-08-04']);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('fullDayOffByStaffAndDate', fn (array $map) => $map === []);
    }

    public function test_substitute_and_compensatory_dates_are_marked_as_no_report_needed(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        // 休日勤務申請: 勤務日(8/1)は日報が要るが、振替休日(8/3)は終日休み。
        $this->approvedLeave($staff, ['type' => 'holiday_work', 'start_date' => '2026-08-01', 'end_date' => '2026-08-01', 'substitute_holiday_date' => '2026-08-03']);
        // 代休申請: 勤務した日(8/2)は日報が要るが、代休日(8/4)は終日休み。
        $this->approvedLeave($staff, ['type' => 'compensatory_leave', 'start_date' => '2026-08-02', 'end_date' => '2026-08-02', 'compensatory_date' => '2026-08-04']);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('fullDayOffByStaffAndDate', function (array $map) use ($staff) {
            return ($map[$staff->id] ?? []) === ['2026-08-03' => true, '2026-08-04' => true];
        });
    }

    /**
     * 承認待ちの申請は却下されることがあるため、日報が不要だと早合点させない。
     */
    public function test_pending_leave_is_not_marked_as_no_report_needed(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        $this->approvedLeave($staff, [
            'type' => 'paid_leave', 'granularity' => 'full_day',
            'start_date' => '2026-08-03', 'end_date' => '2026-08-03',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('fullDayOffByStaffAndDate', fn (array $map) => $map === []);
    }

    public function test_highlights_days_with_a_punch_but_no_daily_report(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        $this->fakeTimecard([$staff->id => [
            '2026-08-03' => ['come' => 480, 'bye' => 1020],
            // 提出済みの日は対象外。
            '2026-08-04' => ['come' => 480, 'bye' => 1020],
        ]]);
        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-04', 'submitted_at' => now()]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('missingReportByStaffAndDate', function (array $map) use ($staff) {
            return ($map[$staff->id] ?? []) === ['2026-08-03' => true];
        });
    }

    /**
     * 休みの日に打刻だけがあっても、日報の提出漏れではない。
     */
    public function test_does_not_highlight_a_punch_on_a_full_day_off(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        $this->approvedLeave($staff, ['type' => 'paid_leave', 'granularity' => 'full_day', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03']);
        $this->fakeTimecard([$staff->id => ['2026-08-03' => ['come' => 480, 'bye' => 1020]]]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('missingReportByStaffAndDate', fn (array $map) => $map === []);
    }

    public function test_no_missing_report_highlight_without_the_timecard_connection(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Staff::factory()->create();

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertViewHas('timecardEnabled', false);
        $response->assertViewHas('missingReportByStaffAndDate', fn (array $map) => $map === []);
        $response->assertSee('タイムカード連携が無効のため');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function approvedLeave(Staff $staff, array $attributes): LeaveRequest
    {
        return LeaveRequest::create([
            'staff_id' => $staff->id,
            'approver_id' => Staff::factory()->create(['is_supervisor' => true])->id,
            'status' => LeaveRequest::STATUS_APPROVED,
            ...$attributes,
        ]);
    }

    /**
     * テスト環境にはtimecard DBが無いため、打刻の取得だけを差し替える。
     *
     * @param  array<int, array<string, array{come: int|null, bye: int|null}>>  $punches
     */
    private function fakeTimecard(array $punches): void
    {
        $this->mock(TimecardService::class, function ($mock) use ($punches) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('punchesFor')->andReturn($punches);
        });
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

    public function test_review_link_shown_only_to_those_who_can_open_the_review_screen(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);
        $sales = Staff::factory()->sales()->create();

        // 上長も内容と確認済/未確認を閲覧できるため導線を出す(確認・差し戻しは日報管理者のみ)。
        foreach ([$manager, $supervisor] as $viewer) {
            $this->actingAs($viewer)->get(route('daily-reports.list.index'))
                ->assertSee(route('daily-reports.review.index', ['date' => '2026-08-10']), false);
        }

        // 営業担当はそもそも作業日報一覧を開けない。
        $this->actingAs($sales)->get(route('daily-reports.list.index'))->assertForbidden();
    }

    /**
     * 有休は1日・半日・2時間(0.25日)単位で数えるため、小数第1位に丸めて
     * 0.25が0.3になってはいけない(2026-08-19の不具合)。
     */
    public function test_paid_leave_column_shows_quarter_days_without_rounding(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create([
            'name' => '有休太郎',
            'paid_leave_granted_current_year' => 14.25,
        ]);

        $this->approvedLeave($staff, [
            'type' => 'paid_leave', 'granularity' => 'hours', 'half_day_period' => 'am',
            'start_date' => '2026-08-03', 'end_date' => '2026-08-03', 'day_count' => 0.25,
        ]);

        $response = $this->actingAs($manager)->get(route('daily-reports.list.index'));

        $response->assertOk();
        $response->assertSee('0.25/14', false);
        $response->assertDontSee('0.3/14.3', false);
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
