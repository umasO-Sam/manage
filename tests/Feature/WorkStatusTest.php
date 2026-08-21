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

    /**
     * 承認待ち(オレンジ)・承認済み(緑)の色分けは権限によらず全員に見せる。
     * 誰がいつ休むかは、承認前であっても全員が予定を立てるのに使うため。
     */
    public function test_general_staff_also_sees_the_approval_status_colours(): void
    {
        $staff = Staff::factory()->create();
        $applicant = Staff::factory()->create(['name' => '申請太郎']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'pending',
        ]);
        LeaveRequest::create([
            'staff_id' => $applicant->id, 'type' => 'paid_leave', 'start_date' => '2026-08-13', 'end_date' => '2026-08-13',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => 'approved',
        ]);

        $content = $this->actingAs($staff)->get(route('work-status.index'))->assertOk()->getContent();

        // セルは短縮表記、マウスを乗せたときは正式名称と決裁の状態を出す。
        $this->assertStringContainsString('>1日休</span>', $content);
        $this->assertStringContainsString('有給休暇（承認待ち）', $content);
        $this->assertStringContainsString('有給休暇（承認済み）', $content);
        $this->assertStringContainsString('bg-amber-500 text-white', $content);
        $this->assertStringContainsString('bg-emerald-500 text-white', $content);
        // 権限で色分けを止めていた頃の灰色のバッジは使わない。
        $this->assertStringNotContainsString('bg-slate-200 text-slate-700', $content);
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
        $response->assertSee('有給休暇（承認済み）');
    }

    /**
     * 同じ日の在宅・休出と半日/2時間の有給休暇は1つにまとめて出す(行を増やさないため)。
     */
    public function test_same_day_telework_or_holiday_work_is_combined_with_a_partial_paid_leave(): void
    {
        $staff = Staff::factory()->create(['name' => '合成太郎']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $make = fn (array $overrides) => LeaveRequest::create([
            'staff_id' => $staff->id, 'approver_id' => $approver->id, 'status' => 'approved', ...$overrides,
        ]);

        // 在宅＋AM半休 → 在A半
        $make(['type' => 'telework', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12']);
        $make([
            'type' => 'paid_leave', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
            'granularity' => 'half_day', 'half_day_period' => 'am', 'day_count' => 0.5,
        ]);
        // 休出＋PM2H有休 → 出P2
        $make([
            'type' => 'holiday_work', 'start_date' => '2026-08-13', 'end_date' => '2026-08-13',
            'order_no' => 'A-1', 'work_location' => '本社', 'no_substitute_needed' => true,
        ]);
        $make([
            'type' => 'paid_leave', 'start_date' => '2026-08-13', 'end_date' => '2026-08-13',
            'granularity' => 'hours', 'half_day_period' => 'pm', 'hours' => 2, 'day_count' => 0.25,
        ]);
        // 在宅＋1日有休は矛盾するのでまとめず、2つのまま出す。
        $make(['type' => 'telework', 'start_date' => '2026-08-14', 'end_date' => '2026-08-14']);
        $make([
            'type' => 'paid_leave', 'start_date' => '2026-08-14', 'end_date' => '2026-08-14',
            'granularity' => 'full_day', 'day_count' => 1.0,
        ]);

        $content = $this->actingAs($staff)->get(route('work-status.index'))->assertOk()->getContent();

        $this->assertStringContainsString('>在A半</a>', $content);
        $this->assertStringContainsString('>出P2</a>', $content);
        $this->assertStringContainsString('テレワーク申請（承認済み）＋有給休暇（承認済み）', $content);
        // まとめた分は在宅・休出の単独表示を残さない。
        $this->assertStringNotContainsString('>休出</a>', $content);
        // 1日有休と在宅はまとめずに両方出す。
        $this->assertStringContainsString('>1日休</a>', $content);
        $this->assertStringContainsString('>在宅</a>', $content);
    }

    /**
     * まとめた表示は2件とも承認済みのときだけ緑にする(片方が未決なら未確定のため橙)。
     */
    public function test_a_combined_chip_stays_amber_until_both_requests_are_approved(): void
    {
        $staff = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $staff->id, 'approver_id' => $approver->id, 'status' => 'approved',
            'type' => 'telework', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
        ]);
        LeaveRequest::create([
            'staff_id' => $staff->id, 'approver_id' => $approver->id, 'status' => 'pending',
            'type' => 'paid_leave', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
            'granularity' => 'half_day', 'half_day_period' => 'pm', 'day_count' => 0.5,
        ]);

        $content = $this->actingAs($staff)->get(route('work-status.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/bg-amber-500[^>]*>在P半</u', $content);
    }

    /**
     * 自分の申請はセルから開ける。他人の分を開けるのは上長・勤怠管理者・役員・
     * 資金管理者・administratorだけ。
     */
    public function test_only_permitted_viewers_get_a_link_to_someone_elses_request(): void
    {
        $applicant = Staff::factory()->create(['name' => '申請太郎']);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $applicant->id, 'approver_id' => $approver->id, 'status' => 'approved',
            'type' => 'paid_leave', 'start_date' => '2026-08-12', 'end_date' => '2026-08-12',
            'granularity' => 'full_day', 'day_count' => 1.0,
        ]);
        $url = route('leave-requests.show', $leaveRequest);

        // 本人は開ける。
        $this->actingAs($applicant)->get(route('work-status.index'))->assertOk()->assertSee($url, false);

        foreach ([
            ['is_supervisor' => true],
            ['is_attendance_manager' => true],
            ['is_executive' => true],
            ['is_fund_manager' => true],
            ['is_administrator' => true],
        ] as $flags) {
            $viewer = Staff::factory()->create($flags);
            $this->actingAs($viewer)->get(route('work-status.index'))->assertOk()->assertSee($url, false);
            $this->actingAs($viewer)->get($url)->assertOk();
        }

        // 一般社員・経理資材担当は他人の分を開けない。
        foreach ([Staff::factory()->create(), Staff::factory()->procurementManager()->create()] as $viewer) {
            $this->actingAs($viewer)->get(route('work-status.index'))->assertOk()->assertDontSee($url, false);
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }

        // 参照ユーザは詳細画面自体が403なので、リンクにしない。
        $viewer = Staff::factory()->viewer()->create();
        $this->actingAs($viewer)->get(route('work-status.index'))->assertOk()->assertDontSee($url, false);
        $this->actingAs($viewer)->get($url)->assertForbidden();
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
