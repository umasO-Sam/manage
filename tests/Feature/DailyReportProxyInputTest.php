<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 勤怠管理者による作業日報の代理入力。提出後は通常どおり確認の対象になり、
 * 差し戻しは本人ではなく代理提出した勤怠管理者に返る。
 */
class DailyReportProxyInputTest extends TestCase
{
    use RefreshDatabase;

    private function attendanceManager(): Staff
    {
        return Staff::factory()->create(['is_attendance_manager' => true, 'name' => '勤怠花子']);
    }

    private function reviewer(): Staff
    {
        return Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(Staff $target): array
    {
        $category = CategoryCode::firstOrCreate(
            ['code' => 59],
            ['major_category' => '製造', 'sub_category' => '機械', 'item_name' => '機械製造']
        );

        return [
            'work_date' => '2026-08-03',
            'staff_id' => $target->id,
            'entries' => [[
                'start_minute' => 540, 'end_minute' => 1020,
                'category_id' => $category->id, 'order_no' => 'AB123-N01',
            ]],
        ];
    }

    public function test_the_attendance_manager_sees_the_target_selector(): void
    {
        $manager = $this->attendanceManager();
        Staff::factory()->create(['name' => '対象次郎']);

        $this->actingAs($manager)->get(route('daily-reports.show'))
            ->assertOk()
            ->assertSee('代理入力する担当者')
            ->assertSee('対象次郎');
    }

    public function test_ordinary_staff_do_not_see_the_target_selector(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('daily-reports.show'))
            ->assertOk()
            ->assertDontSee('代理入力する担当者');
    }

    public function test_the_attendance_manager_can_open_another_persons_report(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象次郎']);

        $this->actingAs($manager)->get(route('daily-reports.show', ['date' => '2026-08-03', 'staff_id' => $target->id]))
            ->assertOk()
            ->assertSee('対象次郎さんの日報を代理で入力しています');
    }

    /** 権限が無い人が staff_id を付けても、黙って自分の日報になる。 */
    public function test_ordinary_staff_cannot_open_someone_elses_report(): void
    {
        $staff = Staff::factory()->create();
        $other = Staff::factory()->create(['name' => '他人三郎']);

        $this->actingAs($staff)->get(route('daily-reports.show', ['staff_id' => $other->id]))
            ->assertOk()
            ->assertDontSee('他人三郎さんの日報を代理で入力しています');
    }

    public function test_a_proxy_submission_creates_the_report_and_labor_for_the_target(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create();

        $this->actingAs($manager)->post(route('daily-reports.store'), $this->payload($target))
            ->assertRedirect();

        $report = DailyReport::sole();
        $this->assertSame($target->id, $report->staff_id, '日報は対象者のものになる');
        $this->assertSame($manager->id, $report->proxy_staff_id);
        $this->assertTrue($report->isProxySubmitted());
        $this->assertNotNull($report->submitted_at);

        // 人工データも対象者のものとして、確認待ち(仮登録)で作られる。
        $labor = LaborCost::sole();
        $this->assertSame($target->id, $labor->staff_id);
        $this->assertTrue($labor->is_provisional);

        $this->assertDatabaseHas('operation_logs', ['action' => OperationLog::ACTION_DAILY_REPORT_PROXY_SUBMIT]);
    }

    /** 権限が無い人が他人のIDを送っても、自分の日報として保存される。 */
    public function test_ordinary_staff_cannot_submit_on_behalf_of_someone_else(): void
    {
        $staff = Staff::factory()->create();
        $other = Staff::factory()->create();

        $this->actingAs($staff)->post(route('daily-reports.store'), $this->payload($other))->assertRedirect();

        $report = DailyReport::sole();
        $this->assertSame($staff->id, $report->staff_id);
        $this->assertNull($report->proxy_staff_id);
    }

    public function test_a_proxy_submitted_report_is_listed_for_review(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象次郎']);
        $this->actingAs($manager)->post(route('daily-reports.store'), $this->payload($target));

        $this->actingAs($this->reviewer())->get(route('daily-reports.review.index', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('対象次郎')
            ->assertSee('代理提出（勤怠花子）');
    }

    public function test_a_rejection_goes_back_to_the_proxy_not_the_target(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象次郎']);
        $this->actingAs($manager)->post(route('daily-reports.store'), $this->payload($target));
        $report = DailyReport::sole();

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '午後の分類が違います',
        ])->assertRedirect();

        // 代理提出者の作業日報画面に、差し戻された分が並ぶ。
        $this->actingAs($manager)->get(route('daily-reports.show'))
            ->assertOk()
            ->assertSee('あなたが代理提出した日報が 1 件差し戻されています')
            ->assertSee('対象次郎')
            ->assertSee('午後の分類が違います');

        $this->assertSame(1, $manager->rejectedProxyReportsQuery()->count());
        $this->assertSame(0, $target->rejectedProxyReportsQuery()->count());
    }

    /** 本人が自分で出し直したら代理の印は外れ、以降の差し戻しは本人に返る。 */
    public function test_the_target_can_overwrite_and_that_clears_the_proxy_mark(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create();
        $this->actingAs($manager)->post(route('daily-reports.store'), $this->payload($target));

        $this->actingAs($target)->post(route('daily-reports.store'), $this->payload($target))->assertRedirect();

        $report = DailyReport::sole();
        $this->assertSame($target->id, $report->staff_id);
        $this->assertNull($report->proxy_staff_id, '本人が出し直したら代理提出ではなくなる');
    }

    /** 代理入力中に日付を動かしても対象者を保つ(自分の日報に化けない)。 */
    public function test_the_date_navigation_keeps_the_target(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create();

        // Blade側で & が &amp; に escape されるため、escape 有りのまま突き合わせる。
        $this->actingAs($manager)->get(route('daily-reports.show', ['date' => '2026-08-03', 'staff_id' => $target->id]))
            ->assertOk()
            ->assertSee(route('daily-reports.show', ['date' => '2026-08-02', 'staff_id' => $target->id]))
            ->assertSee(route('daily-reports.show', ['date' => '2026-08-04', 'staff_id' => $target->id]));
    }
}
