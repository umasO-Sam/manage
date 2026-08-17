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
            // 一覧の補足は1行に収めて省略する
            ->assertSee('overflow: hidden; text-overflow: ellipsis; white-space: nowrap;', false);
    }

    /**
     * 一覧では分類はコードの数字だけを出す。分類名は200文字を超えることがあり、
     * 全文を並べると表が読めなくなるため(名称はマウスを乗せたときと修正フォームで読む)。
     */
    public function test_the_list_shows_only_the_category_code(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = $this->makeCategory('29', 'バルブ・配管材料・レギュレータ・フィルタ・コンバム');
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $manager->id, 'category_id' => $category->id,
            'note' => '収まりきらないくらい長い補足のテキスト',
            'work_hours' => 1, 'work_minutes' => 0, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('labor-records.index'))->assertOk();

        // 一覧のセルの中身はコードだけ。名称はマウスを乗せたときのtitleにだけ出る。
        $response->assertSee('title="29：バルブ・配管材料・レギュレータ・フィルタ・コンバム"', false);
        preg_match(
            '/<td class="p-2\.5 font-mono whitespace-nowrap text-center"\s+title="[^"]*">\s*([^<]*?)\s*<\/td>/u',
            $response->getContent(),
            $cell
        );
        $this->assertSame('29', $cell[1] ?? null, '一覧の分類セルはコードの数字だけにする');
        // 補足は省略表示でも全文をtitleで読める。
        $response->assertSee('title="収まりきらないくらい長い補足のテキスト"', false);
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

    /**
     * 差し戻された日報にぶら下がったままの未確認レコードは、一覧の下に別枠で出す。
     * 確定していないので上の一覧には出ず、確認待ちのバッジからも外れるため、
     * ここに出さないとどこにも現れないまま滞留する。
     */
    public function test_shows_rejected_records_in_a_separate_block(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create(['name' => '差戻太郎']);

        $rejectedReport = DailyReport::create([
            'staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now(),
            'rejected_at' => now(), 'rejection_reason' => '注番が違います',
        ]);
        LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $rejectedReport->id,
            'order_no' => 'REJECTED-1', 'work_hours' => 2, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => true, 'origin' => LaborCost::ORIGIN_PURCHASE_INPUT,
        ]);

        // 差し戻されていない確認待ちは、従来どおりどちらにも出さない(作業日報確認で扱う)。
        $pendingReport = DailyReport::create([
            'staff_id' => $staff->id, 'work_date' => '2026-08-06', 'submitted_at' => now(),
        ]);
        LaborCost::create([
            'work_date' => '2026-08-06', 'staff_id' => $staff->id, 'daily_report_id' => $pendingReport->id,
            'order_no' => 'PENDING-1', 'work_hours' => 2, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => true, 'origin' => LaborCost::ORIGIN_PURCHASE_INPUT,
        ]);

        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            ->assertSee('差し戻し 1件')
            ->assertSee('REJECTED-1')
            ->assertSee('注番が違います')
            ->assertDontSee('PENDING-1')
            // 上の一覧(確定済み)には入れない。
            ->assertViewHas('records', fn ($records) => $records->isEmpty())
            ->assertViewHas('rejectedRecords', fn ($rejected) => $rejected->count() === 1
                && $rejected->first()->order_no === 'REJECTED-1');
    }

    /**
     * 差し戻し枠のレコードも修正できる。ただし修正しても確定させない
     * (未確認のまま＝作業日報確認の対象として残す)。ここで確定にしてしまうと、
     * 誰も内容を確認しないまま原価計算に乗ってしまう。
     */
    public function test_a_rejected_record_can_be_edited_and_stays_unconfirmed(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $report = DailyReport::create([
            'staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now(),
            'rejected_at' => now(), 'rejection_reason' => '注番が違います',
        ]);
        $record = LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'order_no' => 'WRONG-1', 'work_hours' => 2, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => true, 'origin' => LaborCost::ORIGIN_PURCHASE_INPUT,
        ]);

        $this->actingAs($manager)->put(route('labor-records.update', $record), [
            'work_date' => '2026-08-05',
            'staff_id' => $staff->id,
            'order_no' => 'FIXED-1',
            'work_hours' => 3,
            'work_minutes' => 30,
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame('FIXED-1', $record->order_no);
        $this->assertSame(3, $record->work_hours);
        $this->assertTrue($record->is_provisional, '修正しても未確認のまま残る');
        $this->assertSame($report->id, $record->daily_report_id, '日報とのつながりも切れない');

        // 直したあとも差し戻し枠に残り、確定済みの一覧には移らない。
        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            ->assertSee('FIXED-1')
            ->assertViewHas('records', fn ($records) => $records->isEmpty())
            ->assertViewHas('rejectedRecords', fn ($rejected) => $rejected->count() === 1);
    }

    /**
     * 差し戻し枠の操作は由来で出し分ける。
     * 仕入入力の分は日報を開いても直せない（時間帯を持たずグリッドに出ない）ため修正・削除を出し、
     * 作業日報の分は本人が出し直せば作り直されるので触らせず日報へ誘導する。
     */
    public function test_a_rejected_daily_report_record_only_links_to_the_report(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $record = $this->makeRejectedRecord('FROM-REPORT', LaborCost::ORIGIN_DAILY_REPORT);

        // 「修正」「削除」「日報」の語はメニューや説明文にも出るため、その行に固有のURLで判定する。
        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            ->assertSee('FROM-REPORT')
            // 本人が日報を出し直せば作り直されるので、ここでは触らせない。
            ->assertDontSee('labor-records/'.$record->id, false)
            ->assertSee('daily-reports/review?date=2026-08-05', false);
    }

    public function test_a_rejected_purchase_input_record_offers_edit_and_delete(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $record = $this->makeRejectedRecord('FROM-INPUT', LaborCost::ORIGIN_PURCHASE_INPUT);

        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            ->assertSee('FROM-INPUT')
            // 修正フォームと削除フォームの送信先。
            ->assertSee('labor-records/'.$record->id, false)
            // 日報を開いても直せない（時間帯を持たずグリッドに出ない）ため誘導しない。
            ->assertDontSee('daily-reports/review?date=2026-08-05', false);
    }

    /** 差し戻し中の日報にぶら下がる未確認レコードを1件作る。 */
    private function makeRejectedRecord(string $orderNo, string $origin): LaborCost
    {
        $staff = Staff::factory()->create();
        $report = DailyReport::create([
            'staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now(),
            'rejected_at' => now(), 'rejection_reason' => '要修正',
        ]);

        return LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'order_no' => $orderNo, 'work_hours' => 1, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => true, 'origin' => $origin,
        ]);
    }

    /** 差し戻し枠のレコードも削除できる。 */
    public function test_a_rejected_record_can_be_deleted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();
        $report = DailyReport::create([
            'staff_id' => $staff->id, 'work_date' => '2026-08-05', 'submitted_at' => now(),
            'rejected_at' => now(), 'rejection_reason' => '不要な登録でした',
        ]);
        $record = LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'order_no' => 'DELETE-ME', 'work_hours' => 1, 'work_minutes' => 0,
            'is_overtime' => false, 'is_provisional' => true, 'origin' => LaborCost::ORIGIN_PURCHASE_INPUT,
        ]);

        $this->actingAs($manager)->delete(route('labor-records.destroy', $record))->assertRedirect();

        $this->assertDatabaseMissing('labor_costs', ['id' => $record->id]);
    }

    /** 差し戻しが無ければ枠ごと出さない。 */
    public function test_the_rejected_block_is_hidden_when_there_is_none(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            // 画面上部の説明文にも「差し戻し」の語があるため、枠の中身で判定する。
            ->assertDontSee('差し戻し理由')
            ->assertViewHas('rejectedRecords', fn ($rejected) => $rejected->isEmpty());
    }

    /**
     * 差し戻し枠にも一覧と同じ絞り込みを効かせる。片方だけ絞り込まれていると、
     * 日付で絞ったときに無関係な差し戻しが並んで混乱する。
     */
    public function test_the_rejected_block_follows_the_same_filters(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $staff = Staff::factory()->create();

        foreach ([['2026-08-05', 'IN-RANGE'], ['2026-09-05', 'OUT-OF-RANGE']] as [$date, $orderNo]) {
            $report = DailyReport::create([
                'staff_id' => $staff->id, 'work_date' => $date, 'submitted_at' => now(),
                'rejected_at' => now(), 'rejection_reason' => '要修正',
            ]);
            LaborCost::create([
                'work_date' => $date, 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
                'order_no' => $orderNo, 'work_hours' => 1, 'work_minutes' => 0,
                'is_overtime' => false, 'is_provisional' => true, 'origin' => LaborCost::ORIGIN_PURCHASE_INPUT,
            ]);
        }

        $this->actingAs($manager)->get(route('labor-records.index', [
            'date_from' => '2026-08-01', 'date_to' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertSee('IN-RANGE')
            ->assertDontSee('OUT-OF-RANGE');
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
