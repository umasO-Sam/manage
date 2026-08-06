<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 日報管理者フラグ。作業日報の確認は経理資材担当のうち特定のメンバーが行うため、
 * 画面も未確認バッジもそのメンバーだけに絞る。
 */
class DailyReportReviewerFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_procurement_manager_without_the_flag_cannot_open_the_review_screen(): void
    {
        $staff = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => false]);

        $this->actingAs($staff)->get(route('daily-reports.review.index'))->assertForbidden();
    }

    public function test_a_procurement_manager_with_the_flag_can_open_the_review_screen(): void
    {
        $staff = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);

        $this->actingAs($staff)->get(route('daily-reports.review.index'))->assertOk();
    }

    /** フラグだけでは足りない。確認は人工データの確定を伴う経理資材担当の業務。 */
    public function test_the_flag_alone_does_not_grant_access_to_other_roles(): void
    {
        foreach ([Staff::ROLE_GENERAL, Staff::ROLE_SALES] as $role) {
            $staff = Staff::factory()->create(['role' => $role, 'is_daily_report_reviewer' => true]);

            $this->actingAs($staff)->get(route('daily-reports.review.index'))->assertForbidden();
        }
    }

    public function test_an_administrator_can_always_open_the_review_screen(): void
    {
        $staff = Staff::factory()->create(['is_administrator' => true, 'is_daily_report_reviewer' => false]);

        $this->actingAs($staff)->get(route('daily-reports.review.index'))->assertOk();
    }

    public function test_the_menu_and_badge_are_shown_only_to_reviewers(): void
    {
        $author = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $author->id, 'work_date' => '2026-08-10', 'submitted_at' => now()]);
        LaborCost::create([
            'work_date' => '2026-08-10', 'staff_id' => $author->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => true,
        ]);

        $reviewer = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);
        $html = $this->actingAs($reviewer)->get(route('my-calendar.show'))->getContent();
        $this->assertStringContainsString('作業日報確認', $html);

        $other = Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => false]);
        $html = $this->actingAs($other)->get(route('my-calendar.show'))->getContent();
        $this->assertStringNotContainsString('作業日報確認', $html);
        // 経理資材担当としての他のメニューは残る
        $this->assertStringContainsString('人工レコード', $html);
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
