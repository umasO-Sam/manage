<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Services\TimecardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * タイムカード(timecard-new)連携。テスト環境にはtimecard DBが無いため、
 * 「連携が無効なときに何も壊さない」ことと、乖離判定のロジックを検証する。
 */
class TimecardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_is_disabled_when_the_connection_is_not_configured(): void
    {
        $service = app(TimecardService::class);

        $this->assertFalse($service->isEnabled());
        $this->assertSame([], $service->punchesFor(collect([Staff::factory()->create()]), now(), now()));
        $this->assertSame([], $service->staffChoices());
    }

    public function test_daily_report_screens_work_without_the_timecard_connection(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('daily-reports.show'))->assertOk();
        $this->actingAs($manager)->get(route('daily-reports.review.index'))->assertOk();
    }

    public function test_no_warning_when_the_punch_is_close_to_the_report(): void
    {
        $service = app(TimecardService::class);

        // 打刻 7:50/17:20 に対し日報 8:00〜17:10。差は10分で閾値(30分)以内。
        $this->assertNull($service->divergenceWarning(
            ['come' => 7 * 60 + 50, 'bye' => 17 * 60 + 20],
            8 * 60,
            17 * 60 + 10
        ));
    }

    public function test_warns_when_the_report_diverges_from_the_punch(): void
    {
        $service = app(TimecardService::class);

        // 打刻 7:50 出勤・20:00 退勤に対し、日報が 8:00〜17:10 のまま(退勤が2時間50分ずれる)。
        $warning = $service->divergenceWarning(
            ['come' => 7 * 60 + 50, 'bye' => 20 * 60],
            8 * 60,
            17 * 60 + 10
        );

        $this->assertNotNull($warning);
        $this->assertStringContainsString('退勤打刻 20:00', $warning);
        $this->assertStringContainsString('170分差', $warning);
        // 出勤側は10分差なので触れない。
        $this->assertStringNotContainsString('出勤打刻', $warning);
    }

    public function test_no_warning_when_there_is_no_punch_or_no_entries(): void
    {
        $service = app(TimecardService::class);

        $this->assertNull($service->divergenceWarning(null, 8 * 60, 17 * 60));
        $this->assertNull($service->divergenceWarning(['come' => 8 * 60, 'bye' => 17 * 60], null, null));
        // 未退勤(打刻が片方だけ)なら、その項目は判定しない。
        $this->assertNull($service->divergenceWarning(['come' => 8 * 60, 'bye' => null], 8 * 60, 23 * 60));
    }

    /**
     * タイムカードの担当者ID(wid)は staff.sid と同じ値を使う。本番の実データで
     * SID保有30人中29人が一致・不一致0だったため、専用の列は持たない。
     */
    public function test_the_sid_is_used_to_match_timecard_punches(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create(['sid' => null]);

        $this->actingAs($manager)->put(route('staff.update', $staff), [
            'name' => $staff->name,
            'department' => $staff->department,
            'login_id' => $staff->login_id,
            'email' => $staff->email,
            'role' => $staff->role,
            'sid' => 37,
        ])->assertRedirect(route('staff.index'));

        $this->assertSame(37, $staff->fresh()->sid);
    }
}
