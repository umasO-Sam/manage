<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaborRecordTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(string $code, string $itemName): CategoryCode
    {
        return CategoryCode::create([
            'code' => $code, 'major_category' => '社内', 'sub_category' => '製造', 'item_name' => $itemName,
        ]);
    }

    public function test_procurement_manager_can_view_the_page(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('labor-records.index'))->assertOk();
    }

    /**
     * 修正フォームの幅を決めていたのは分類のセレクト。selectの既定幅は最も長い選択肢で
     * 決まり、分類名は200文字を超えるものがあるため、幅を指定しないと表ごと数千pxに
     * 広がって保存ボタンまで右に見切れる。幅を固定していることを固定する。
     */
    public function test_the_category_select_has_a_fixed_width_so_the_table_does_not_stretch(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $longName = str_repeat('バルブ・配管材料・レギュレータ・', 12);
        $category = $this->makeCategory('29', $longName);
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $manager->id, 'category_id' => $category->id,
            'work_hours' => 1, 'work_minutes' => 0, 'is_provisional' => false,
        ]);

        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            // 編集フォームの分類セレクト
            ->assertSee('style="width: 16rem;"', false)
            // 一覧の分類・補足は折り返して複数行にする
            ->assertSee('min-width: 11rem; max-width: 16rem; word-break: break-word;', false)
            ->assertSee('min-width: 8rem; max-width: 14rem; word-break: break-word;', false);
    }

    public function test_supervisor_cannot_access_the_page(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($supervisor)->get(route('labor-records.index'))->assertForbidden();
    }

    public function test_general_staff_cannot_access_the_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('labor-records.index'))->assertForbidden();
    }

    public function test_shows_confirmed_daily_report_and_purchase_input_records(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create(['name' => '作業太郎']);
        $report = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);

        // 日報確認で確定したレコード
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'order_no' => 'AAA-111', 'work_hours' => 4, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => false,
        ]);
        // 仕入管理データ入力で登録したレコード
        LaborCost::create([
            'work_date' => '2026-08-06', 'staff_id' => $staff->id,
            'order_no' => 'BBB-222', 'work_hours' => 3, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('labor-records.index'));

        $response->assertOk();
        $response->assertSee('AAA-111');
        $response->assertSee('BBB-222');
        $response->assertSee('作業日報');
        $response->assertSee('仕入入力');
    }

    public function test_hides_provisional_records_awaiting_review(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);

        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'order_no' => 'PROVISIONAL-1', 'work_hours' => 4, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => true,
        ]);

        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            ->assertDontSee('PROVISIONAL-1');
    }

    public function test_filters_by_order_no(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $staff->id, 'order_no' => 'HIT-111',
            'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $staff->id, 'order_no' => 'MISS-222',
            'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);

        $this->actingAs($manager)->get(route('labor-records.index', ['order_no' => 'HIT']))
            ->assertOk()
            ->assertSee('HIT-111')
            ->assertDontSee('MISS-222');
    }

    public function test_filters_by_date_range_including_boundary_days(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        LaborCost::create(['work_date' => '2026-08-01', 'staff_id' => $staff->id, 'order_no' => 'START-DAY',
            'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2026-08-31', 'staff_id' => $staff->id, 'order_no' => 'END-DAY',
            'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2026-09-01', 'staff_id' => $staff->id, 'order_no' => 'OUTSIDE',
            'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);

        // 期間の両端の日が漏れずに含まれることを確認する(日付カラムの比較漏れの回帰防止)。
        $this->actingAs($manager)->get(route('labor-records.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('START-DAY')
            ->assertSee('END-DAY')
            ->assertDontSee('OUTSIDE');
    }

    public function test_filters_by_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create(['name' => '対象太郎']);
        $other = Staff::factory()->create(['name' => '対象外次郎']);

        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $target->id, 'order_no' => 'TARGET-1',
            'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $other->id, 'order_no' => 'OTHER-1',
            'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);

        $this->actingAs($manager)->get(route('labor-records.index', ['staff_id' => $target->id]))
            ->assertOk()
            ->assertSee('TARGET-1')
            ->assertDontSee('OTHER-1');
    }

    public function test_filters_by_category(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $target = $this->makeCategory('60', '機械組立');
        $other = $this->makeCategory('61', '電気配線');

        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $staff->id, 'category_id' => $target->id,
            'order_no' => 'CAT-HIT', 'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $staff->id, 'category_id' => $other->id,
            'order_no' => 'CAT-MISS', 'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);

        $this->actingAs($manager)->get(route('labor-records.index', ['category_id' => $target->id]))
            ->assertOk()
            ->assertSee('CAT-HIT')
            ->assertDontSee('CAT-MISS');
    }

    public function test_filters_by_source(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $report = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now()]);

        // 仕入管理から入れた人工も日報にぶら下がるため、daily_report_idの有無ではなく
        // originで見分ける。どちらも同じ日報に属する状況を再現する。
        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'origin' => LaborCost::ORIGIN_DAILY_REPORT,
            'order_no' => 'FROM-REPORT', 'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'origin' => LaborCost::ORIGIN_PURCHASE_INPUT,
            'order_no' => 'FROM-INPUT', 'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false]);

        $this->actingAs($manager)->get(route('labor-records.index', ['source' => 'purchase_input']))
            ->assertOk()
            ->assertSee('FROM-INPUT')
            ->assertDontSee('FROM-REPORT');

        $this->actingAs($manager)->get(route('labor-records.index', ['source' => 'daily_report']))
            ->assertOk()
            ->assertSee('FROM-REPORT')
            ->assertDontSee('FROM-INPUT');
    }
}
