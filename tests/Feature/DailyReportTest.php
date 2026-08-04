<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_staff_can_view_the_daily_report_screen(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('daily-reports.show'))->assertOk();
    }

    public function test_submit_button_says_teishutsu_before_first_submission(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->get(route('daily-reports.show', ['date' => '2026-08-03']));

        $response->assertSee('>提出<', false);
        $response->assertDontSee('修正提出');
    }

    public function test_submit_button_says_shusei_teishutsu_after_first_submission(): void
    {
        $staff = Staff::factory()->create();
        DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-03', 'submitted_at' => now()]);

        $response = $this->actingAs($staff)->get(route('daily-reports.show', ['date' => '2026-08-03']));

        $response->assertSee('修正提出');
    }

    public function test_show_screen_includes_the_work_time_summary_panel(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->get(route('daily-reports.show', ['date' => '2026-08-03']));

        $response->assertOk();
        $response->assertSee('労働時間集計');
        $response->assertSee('うち8時間超過分');
        $response->assertSee('うち週40時間超過分');
        $response->assertSee('うち月60時間超過分');
    }

    public function test_show_screen_warns_when_monthly_overtime_hard_cap_is_reached(): void
    {
        $staff = Staff::factory()->create();

        // 20日締めの当月(2026-07-21〜2026-08-20)に平日10日、1日18時間ずつ働かせ、
        // 残業(18-8)*10=100時間ちょうどにする。
        foreach (['2026-07-21', '2026-07-22', '2026-07-23', '2026-07-24', '2026-07-27', '2026-07-28', '2026-07-29', '2026-07-30', '2026-07-31', '2026-08-03'] as $date) {
            LaborCost::create(['work_date' => $date, 'staff_id' => $staff->id, 'work_hours' => 18, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        }

        $response = $this->actingAs($staff)->get(route('daily-reports.show', ['date' => '2026-08-04']));

        $response->assertSee('今月の残業時間が単月100時間の上限に達しています');
    }

    public function test_rejected_report_shows_the_rejection_reason(): void
    {
        $staff = Staff::factory()->create();
        DailyReport::create([
            'staff_id' => $staff->id, 'work_date' => '2026-08-03', 'submitted_at' => now(),
            'rejected_at' => now(), 'rejection_reason' => '注番が間違っています',
        ]);

        $response = $this->actingAs($staff)->get(route('daily-reports.show', ['date' => '2026-08-03']));

        $response->assertSee('差戻し');
        $response->assertSee('注番が間違っています');
    }

    public function test_draft_save_does_not_generate_labor_costs(): void
    {
        $staff = Staff::factory()->create();
        $category = CategoryCode::create(['code' => 59, 'major_category' => '製造', 'sub_category' => '機械', 'item_name' => '機械製造']);

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-03',
            'entries' => [
                ['start_minute' => 480, 'end_minute' => 600, 'order_no' => 'AB123-N01', 'category_id' => $category->id],
            ],
        ])->assertRedirect();

        $report = DailyReport::where('staff_id', $staff->id)->whereDate('work_date', '2026-08-03')->first();
        $this->assertNotNull($report);
        $this->assertNull($report->submitted_at);
        $this->assertSame(1, $report->entries()->count());
        $this->assertSame(0, LaborCost::count());
    }

    public function test_submitting_groups_entries_and_creates_provisional_labor_costs(): void
    {
        $staff = Staff::factory()->create(['position_weight' => 1.5]);
        $category = CategoryCode::create(['code' => 59, 'major_category' => '製造', 'sub_category' => '機械', 'item_name' => '機械製造']);

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-03',
            'submit' => '1',
            'entries' => [
                // break, excluded from labor costs
                ['start_minute' => 600, 'end_minute' => 610, 'is_break' => true],
                // two segments of the same order/category, should be summed into one LaborCost row
                ['start_minute' => 420, 'end_minute' => 600, 'order_no' => 'AB123-N01', 'category_id' => $category->id],
                ['start_minute' => 610, 'end_minute' => 720, 'order_no' => 'AB123-N01', 'category_id' => $category->id],
                // free-text "other" entry
                ['start_minute' => 720, 'end_minute' => 780, 'order_no' => 'AB123-N01', 'is_other' => true, 'free_text' => '会議'],
            ],
        ])->assertRedirect();

        $report = DailyReport::where('staff_id', $staff->id)->whereDate('work_date', '2026-08-03')->first();
        $this->assertNotNull($report->submitted_at);

        $rows = LaborCost::where('daily_report_id', $report->id)->get();
        $this->assertCount(2, $rows);

        $categoryRow = $rows->firstWhere('category_id', $category->id);
        $this->assertSame(4, $categoryRow->work_hours);
        $this->assertSame(50, $categoryRow->work_minutes);
        $this->assertTrue($categoryRow->is_provisional);
        $this->assertSame(1.5, (float) $categoryRow->position_weight_cache);

        $otherRow = $rows->firstWhere('category_id', null);
        $this->assertSame(1, $otherRow->work_hours);
        $this->assertSame(0, $otherRow->work_minutes);
        $this->assertSame('会議', $otherRow->note);
    }

    public function test_resubmitting_resets_previously_confirmed_labor_costs_to_provisional(): void
    {
        $staff = Staff::factory()->create();
        $category = CategoryCode::create(['code' => 60, 'major_category' => '製造', 'item_name' => '製造']);

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-03',
            'submit' => '1',
            'entries' => [
                ['start_minute' => 420, 'end_minute' => 480, 'order_no' => 'AB123-N01', 'category_id' => $category->id],
            ],
        ]);

        $report = DailyReport::where('staff_id', $staff->id)->first();
        LaborCost::where('daily_report_id', $report->id)->update(['is_provisional' => false]);

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-03',
            'submit' => '1',
            'entries' => [
                ['start_minute' => 420, 'end_minute' => 540, 'order_no' => 'AB123-N01', 'category_id' => $category->id],
            ],
        ]);

        $rows = LaborCost::where('daily_report_id', $report->id)->get();
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->is_provisional);
        $this->assertSame(2, $rows->first()->work_hours);
    }

    public function test_leave_entry_survives_a_page_reload_without_becoming_uncategorized(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-03',
            'entries' => [
                ['start_minute' => 480, 'end_minute' => 600, 'is_leave' => 1, 'leave_type' => 'hours'],
            ],
        ])->assertRedirect();

        $response = $this->actingAs($staff)->get(route('daily-reports.show', ['date' => '2026-08-03']));

        $response->assertOk();

        // initialEntriesにis_leave/leave_typeが含まれていないと、再読み込み時にJS側の
        // entry.is_leaveがundefinedになりカテゴリなし扱い(未分類)になってしまう不具合の回帰確認。
        // Js::from()自体が生成するエスケープ済み断片(先頭の{と末尾の}は実際のオブジェクトでは
        // 前後に他のキーが続くため取り除く)と比較することで、手打ちのエスケープ表記ミスを避ける。
        $wrapped = (string) \Illuminate\Support\Js::from(['is_leave' => true, 'leave_type' => 'hours']);
        preg_match('/^JSON\.parse\(\'\{(.*)\}\'\)$/', $wrapped, $matches);
        $expectedFragment = $matches[1];

        $this->assertStringContainsString($expectedFragment, $response->getContent());
    }

    public function test_category_entry_without_order_no_is_dropped_unless_exempt(): void
    {
        $staff = Staff::factory()->create();
        $regular = CategoryCode::create(['code' => 59, 'major_category' => '社内人工', 'sub_category' => '機械製缶', 'item_name' => '機械製造']);
        $exempt = CategoryCode::create(['code' => 70, 'major_category' => '雑人工', 'sub_category' => '管理', 'item_name' => '調整・工程会議']);

        $this->actingAs($staff)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-03',
            'entries' => [
                // 注番の無い通常分類(59)は保存されない
                ['start_minute' => 480, 'end_minute' => 540, 'category_id' => $regular->id],
                // 除外対象の分類(70:管理)は注番が無くても保存される
                ['start_minute' => 540, 'end_minute' => 600, 'category_id' => $exempt->id],
            ],
        ])->assertRedirect();

        $report = DailyReport::where('staff_id', $staff->id)->whereDate('work_date', '2026-08-03')->first();
        $this->assertSame(1, $report->entries()->count());
        $this->assertSame($exempt->id, $report->entries()->first()->category_id);
    }

    public function test_purchasing_labor_screen_hides_provisional_rows_by_default_and_shows_them_when_included(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $category = CategoryCode::create(['code' => 61, 'major_category' => '製造', 'item_name' => '製造']);

        LaborCost::create([
            'work_date' => '2026-08-03', 'staff_id' => $staff->id, 'order_no' => 'AB123-N01',
            'category_id' => $category->id, 'work_hours' => 3, 'work_minutes' => 0, 'is_provisional' => true,
        ]);

        $default = $this->actingAs($manager)->get(route('purchasing.labor.index', ['date_from' => '2026-08-01']));
        $default->assertDontSee('AB123-N01');

        $withProvisional = $this->actingAs($manager)->get(route('purchasing.labor.index', ['date_from' => '2026-08-01', 'include_provisional' => 1]));
        $withProvisional->assertSee('AB123-N01');
        $withProvisional->assertSee('仮');
    }

    public function test_bulk_confirm_flips_provisional_rows_to_confirmed(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        $laborCost = LaborCost::create([
            'work_date' => '2026-08-03', 'staff_id' => $staff->id, 'work_hours' => 3, 'work_minutes' => 0, 'is_provisional' => true,
        ]);

        $this->actingAs($manager)->post(route('purchasing.labor.bulk-confirm'), [
            'ids' => [$laborCost->id],
        ])->assertRedirect();

        $this->assertFalse($laborCost->fresh()->is_provisional);
    }

    public function test_bulk_confirm_is_forbidden_for_non_procurement_managers(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->post(route('purchasing.labor.bulk-confirm'), ['ids' => []])->assertForbidden();
    }
}
