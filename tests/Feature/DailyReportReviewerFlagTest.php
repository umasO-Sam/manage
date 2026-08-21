<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 日報管理者フラグ。作業日報確認は経理資材担当・上長・役員・資金管理者が閲覧でき、
 * 確認(人工データの確定)と差し戻しはフラグを立てた人だけが行う。
 * フラグはロールを問わず、これだけで画面を開いて確認・差し戻しができる(2026-08-21)。
 */
class DailyReportReviewerFlagTest extends TestCase
{
    use RefreshDatabase;

    /** 確認待ちの日報を1件作り、[日報, 提出者]を返す。 */
    private function pendingReport(): array
    {
        $author = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $author->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-10', 'staff_id' => $author->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        return [$report, $author];
    }

    public function test_managers_supervisors_and_executives_can_open_the_review_screen(): void
    {
        foreach ([
            '経理資材担当' => Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => false]),
            '上長' => Staff::factory()->create(['is_supervisor' => true]),
            '役員' => Staff::factory()->create(['is_executive' => true]),
            '資金管理者' => Staff::factory()->create(['is_fund_manager' => true]),
            'administrator' => Staff::factory()->create(['is_administrator' => true]),
        ] as $label => $staff) {
            $this->actingAs($staff)->get(route('daily-reports.review.index'))
                ->assertOk("{$label}が作業日報確認を開けません。");
        }
    }

    public function test_general_and_sales_staff_cannot_open_the_review_screen(): void
    {
        foreach ([Staff::ROLE_GENERAL, Staff::ROLE_SALES] as $role) {
            $staff = Staff::factory()->create(['role' => $role]);

            $this->actingAs($staff)->get(route('daily-reports.review.index'))->assertForbidden();
        }
    }

    /**
     * 日報管理者フラグはロールを問わない(2026-08-21)。フラグだけで画面を開き、確認までできる。
     * 以前は経理資材担当を兼ねていないと画面ごと開けず、フラグが何の役にも立たなかった。
     */
    public function test_the_reviewer_flag_alone_grants_the_review_screen_whatever_the_role(): void
    {
        [$report] = $this->pendingReport();

        foreach ([Staff::ROLE_GENERAL, Staff::ROLE_SALES] as $role) {
            $staff = Staff::factory()->create(['role' => $role, 'is_daily_report_reviewer' => true]);

            $this->actingAs($staff)->get(route('daily-reports.review.index', ['date' => '2026-08-10']))
                ->assertOk()
                ->assertSee('確認する')
                ->assertSee('差し戻す');

            // メニューからも辿れること(画面に入る道が無いと結局使えないため)。
            $this->actingAs($staff)->get(route('my-calendar.show'))
                ->assertOk()
                ->assertSee(route('daily-reports.review.index'), false);
        }

        $reviewer = Staff::factory()->create(['is_daily_report_reviewer' => true]);
        $this->actingAs($reviewer)->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertRedirect();
        $this->assertFalse(LaborCost::where('daily_report_id', $report->id)->first()->is_provisional);
    }

    public function test_only_a_flagged_reviewer_can_confirm_or_reject(): void
    {
        [$report] = $this->pendingReport();

        // 閲覧はできるが操作はできない
        $viewer = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => false]);
        $this->actingAs($viewer)->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertForbidden();
        $this->assertTrue(LaborCost::where('daily_report_id', $report->id)->first()->is_provisional);

        $supervisor = Staff::factory()->create(['is_supervisor' => true]);
        $this->actingAs($supervisor)->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject', 'rejection_reason' => '内容を確認してください',
        ])->assertForbidden();
        $this->assertNull($report->fresh()->rejected_at);

        $reviewer = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);
        $this->actingAs($reviewer)->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertRedirect();
        $this->assertFalse(LaborCost::where('daily_report_id', $report->id)->first()->is_provisional);
    }

    public function test_the_action_buttons_are_shown_only_to_a_flagged_reviewer(): void
    {
        $this->pendingReport();

        $reviewer = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);
        $this->actingAs($reviewer)->get(route('daily-reports.review.index', ['date' => '2026-08-10']))
            ->assertOk()
            ->assertSee('確認する')
            ->assertSee('差し戻す');

        $viewer = Staff::factory()->create(['is_supervisor' => true]);
        $this->actingAs($viewer)->get(route('daily-reports.review.index', ['date' => '2026-08-10']))
            ->assertOk()
            ->assertDontSee('確認する')
            ->assertDontSee('差し戻す')
            ->assertSee('確認・差し戻しは日報管理者が行います');
    }

    /** 確認済の日報も一覧に残し、状態を読み取れるようにする。 */
    public function test_confirmed_reports_stay_listed_with_their_status(): void
    {
        [$report, $author] = $this->pendingReport();

        $viewer = Staff::factory()->create(['is_supervisor' => true]);
        $this->actingAs($viewer)->get(route('daily-reports.review.index', ['date' => '2026-08-10']))
            ->assertOk()->assertSee($author->name)->assertSee('未確認');

        LaborCost::where('daily_report_id', $report->id)->update(['is_provisional' => false]);

        $this->actingAs($viewer)->get(route('daily-reports.review.index', ['date' => '2026-08-10']))
            ->assertOk()->assertSee($author->name)->assertSee('確認済');
    }

    public function test_the_unconfirmed_badge_is_shown_only_to_a_flagged_reviewer(): void
    {
        $this->pendingReport();

        // このテストにはカードも休暇申請も無いため、赤バッジは未確認日報のものだけ。
        $reviewer = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);
        $this->assertStringContainsString(
            'bg-red-500',
            $this->actingAs($reviewer)->get(route('my-calendar.show'))->getContent()
        );

        $viewer = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => false]);
        $html = $this->actingAs($viewer)->get(route('my-calendar.show'))->getContent();
        $this->assertStringNotContainsString('bg-red-500', $html);
        // 画面自体は開けるのでメニューには残る
        $this->assertStringContainsString('作業日報確認', $html);
    }

    public function test_the_flag_can_be_set_from_the_staff_screens(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => false]);

        $payload = [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
        ];

        $this->actingAs($manager)->put(route('staff.update', $target), $payload + ['is_daily_report_reviewer' => '1'])
            ->assertRedirect();
        $this->assertTrue($target->fresh()->is_daily_report_reviewer);

        $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [$target->id => $payload + ['is_daily_report_reviewer' => '0']],
        ])->assertSessionHasNoErrors()->assertRedirect(route('staff.index'));
        $this->assertFalse($target->fresh()->is_daily_report_reviewer);
    }
}
