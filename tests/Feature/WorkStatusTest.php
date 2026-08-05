<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 2026-08-10(月)を「今日」に固定し、表示範囲を2026-08-03〜2026-09-06(35日)にする。
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

        $this->actingAs($staff)->get(route('work-status.index'))->assertOk();
    }

    public function test_shows_35_days_from_one_week_ago_to_four_weeks_ahead(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->get(route('work-status.index'));

        $response->assertOk();
        $response->assertViewHas('dates', function (array $dates) {
            return $dates[0] === '2026-08-03' && end($dates) === '2026-09-06' && count($dates) === 35;
        });
    }

    public function test_general_staff_sees_neutral_badges_without_approval_status(): void
    {
        $staff = Staff::factory()->create();
        $applicant = Staff::factory()->create(['name' => '申請太郎']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'pending',
        ]);

        $response = $this->actingAs($staff)->get(route('work-status.index'));

        $response->assertOk();
        // 一般社員には承認状況が見えないため、種別のみ表示され、承認状況の文言は出ない。
        $response->assertSee('1日有休');
        $response->assertDontSee('（未承認）');
        $response->assertDontSee('（承認済み）');
    }

    public function test_supervisor_sees_approval_status(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);
        $applicant = Staff::factory()->create(['name' => '申請太郎']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'approved',
        ]);

        $response = $this->actingAs($supervisor)->get(route('work-status.index'));

        $response->assertOk();
        $response->assertSee('1日有休（承認済み）');
    }

    public function test_daily_report_status_is_not_shown(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->get(route('work-status.index'));

        $response->assertOk();
        // 「作業日報」自体はナビゲーションの常設リンクとして出るため、日報ステータス
        // 特有の文言・作業日報確認へのリンクが無いことで判定する。
        $response->assertDontSee('確認待ち');
        $response->assertDontSee('作業日報：');
        $response->assertDontSee(route('daily-reports.review.index', ['date' => '2026-08-10']), false);
    }

    public function test_staff_are_grouped_by_department_then_ordered_by_display_order(): void
    {
        $viewer = Staff::factory()->create(['department' => '役員']);
        Staff::factory()->create(['name' => '製造二郎', 'department' => '製造', 'display_order' => 2]);
        Staff::factory()->create(['name' => '製造太郎', 'department' => '製造', 'display_order' => 1]);
        Staff::factory()->create(['name' => '営業花子', 'department' => '営業', 'display_order' => 1]);

        $response = $this->actingAs($viewer)->get(route('work-status.index'));

        $content = $response->getContent();
        $this->assertLessThan(strpos($content, '製造太郎'), strpos($content, '営業花子'));
        $this->assertLessThan(strpos($content, '製造二郎'), strpos($content, '製造太郎'));
        // 部署名は部署単位でまとめて1回だけ(先頭行の rowspan="2" で)出力される。
        $this->assertSame(1, substr_count($content, 'rowspan="2"'));
    }
}
