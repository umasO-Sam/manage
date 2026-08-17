<?php

namespace Tests\Feature;

use App\Mail\DailyReportRejectedMail;
use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 仕入管理のデータ入力(社内人工・エクセル一括)で入れた人工を、作業日・担当者から
 * 「その日の日報」として扱い、作業日報の確認対象に載せる。
 */
class PurchaseInputLaborAsDailyReportTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): Staff
    {
        return Staff::factory()->procurementManager()->create();
    }

    private function reviewer(): Staff
    {
        return Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);
    }

    private function category(int $code = 63): CategoryCode
    {
        return CategoryCode::firstOrCreate(
            ['code' => $code],
            ['major_category' => '社内人工', 'sub_category' => '管理', 'item_name' => '社内管理業務']
        );
    }

    /** @return array<string, mixed> */
    private function laborPayload(Staff $worker, array $overrides = []): array
    {
        return [
            'form_type' => 'labor',
            'work_date' => '2026-08-05',
            'staff_id' => $worker->id,
            'order_no' => 'HI016-N03',
            'labor_category_id' => $this->category()->id,
            'work_hours' => 2,
            'work_minutes' => 0,
            ...$overrides,
        ];
    }

    public function test_single_labor_entry_is_attached_to_that_days_report(): void
    {
        $worker = Staff::factory()->create(['position_weight' => 1]);

        $this->actingAs($this->manager())->post(route('purchasing.input.store'), $this->laborPayload($worker))
            ->assertRedirect(route('purchasing.input'));

        $report = DailyReport::sole();
        $this->assertSame($worker->id, $report->staff_id);
        $this->assertSame('2026-08-05', $report->work_date->toDateString());
        $this->assertNotNull($report->submitted_at, '確認画面は提出済みのものだけを並べるため');

        $labor = LaborCost::sole();
        $this->assertSame($report->id, $labor->daily_report_id);
        $this->assertSame(LaborCost::ORIGIN_PURCHASE_INPUT, $labor->origin);
        $this->assertTrue($labor->is_provisional, '確認されるまでは未確認のまま');
    }

    /** 同じ担当者・同じ日に2件入れても、日報は1人1日1枚にまとまる。 */
    public function test_two_entries_on_the_same_day_share_one_report(): void
    {
        $worker = Staff::factory()->create(['position_weight' => 1]);
        $manager = $this->manager();

        $this->actingAs($manager)->post(route('purchasing.input.store'), $this->laborPayload($worker));
        $this->actingAs($manager)->post(route('purchasing.input.store'), $this->laborPayload($worker, ['order_no' => 'X-2']));

        $this->assertSame(1, DailyReport::count());
        $this->assertSame(2, LaborCost::where('daily_report_id', DailyReport::sole()->id)->count());
    }

    /** 作業日・担当者が欠けている仮登録は日報を特定できないため、紐づけない。 */
    public function test_a_provisional_entry_without_date_or_staff_is_not_attached(): void
    {
        $this->actingAs($this->manager())->post(route('purchasing.input.store'), [
            'form_type' => 'labor',
            'is_provisional' => '1',
            'work_hours' => 1,
            'work_minutes' => 0,
        ])->assertRedirect(route('purchasing.input'));

        $this->assertSame(0, DailyReport::count());
        $labor = LaborCost::sole();
        $this->assertNull($labor->daily_report_id);
        $this->assertTrue($labor->is_provisional);
    }

    public function test_it_shows_up_on_the_daily_report_review_screen(): void
    {
        $worker = Staff::factory()->create(['name' => '人工太郎', 'position_weight' => 1]);
        $this->actingAs($this->manager())->post(route('purchasing.input.store'),
            $this->laborPayload($worker, ['note' => '現場応援']));

        $this->actingAs($this->reviewer())->get(route('daily-reports.review.index', ['date' => '2026-08-05']))
            ->assertOk()
            ->assertSee('人工太郎')
            ->assertSee('仕入管理のデータ入力から登録された人工')
            ->assertSee('HI016-N03')
            ->assertSee('現場応援')
            ->assertSee('未確認');
    }

    public function test_confirming_the_report_confirms_the_labor(): void
    {
        $worker = Staff::factory()->create(['position_weight' => 1]);
        $this->actingAs($this->manager())->post(route('purchasing.input.store'), $this->laborPayload($worker));
        $report = DailyReport::sole();

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertRedirect();

        $this->assertFalse(LaborCost::sole()->is_provisional);
    }

    /**
     * 本人がその日の作業日報を出し直しても、仕入管理から入れた人工は消えない
     * (以前は daily_report_id だけで見分けていたため、まとめて作り直されていた)。
     */
    public function test_resubmitting_the_daily_report_keeps_the_purchase_input_labor(): void
    {
        $worker = Staff::factory()->create(['position_weight' => 1]);
        $category = $this->category();

        $this->actingAs($this->manager())->post(route('purchasing.input.store'),
            $this->laborPayload($worker, ['order_no' => 'FROM-INPUT']));

        $this->actingAs($worker)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-05',
            'entries' => [[
                'start_minute' => 540, 'end_minute' => 1020,
                'category_id' => $category->id, 'order_no' => 'FROM-REPORT',
            ]],
        ])->assertRedirect();

        $this->assertSame(1, DailyReport::count(), '同じ日・同じ担当者なので日報は1枚のまま');
        $this->assertSame(1, LaborCost::where('order_no', 'FROM-INPUT')->count(), '仕入管理から入れた分は残る');
        $this->assertSame(1, LaborCost::where('order_no', 'FROM-REPORT')->count());

        // もう一度出し直しても、日報由来の分だけが作り直される。
        $this->actingAs($worker)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-05',
            'entries' => [[
                'start_minute' => 540, 'end_minute' => 720,
                'category_id' => $category->id, 'order_no' => 'FROM-REPORT',
            ]],
        ])->assertRedirect();

        $this->assertSame(1, LaborCost::where('order_no', 'FROM-INPUT')->count());
        $this->assertSame(1, LaborCost::where('order_no', 'FROM-REPORT')->count());
        $this->assertSame(LaborCost::ORIGIN_DAILY_REPORT, LaborCost::where('order_no', 'FROM-REPORT')->sole()->origin);
    }

    /**
     * 差し戻しても人工レコードは消えない。未確認(is_provisional=true)のまま残り、
     * 原価計算には乗らない状態で保持される。差し戻しが変えるのは日報の状態だけ。
     */
    public function test_rejecting_the_report_keeps_the_labor_as_unconfirmed(): void
    {
        Mail::fake();
        $worker = Staff::factory()->create(['position_weight' => 1]);
        $this->actingAs($this->manager())->post(route('purchasing.input.store'), $this->laborPayload($worker));
        $report = DailyReport::sole();

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '注番が違います',
        ])->assertRedirect();

        $labor = LaborCost::sole();
        $this->assertTrue($labor->is_provisional, '差し戻しても消えず、未確認のまま残る');
        $this->assertSame(LaborCost::ORIGIN_PURCHASE_INPUT, $labor->origin);
        $this->assertSame($report->id, $labor->daily_report_id, '日報とのつながりも切れない');
        $this->assertNotNull($report->fresh()->rejected_at);
    }

    /**
     * 差し戻し中の人工は「人工レコード」画面には出ない(確定済みだけを扱う画面のため)。
     * 作業日報の確認画面で日付を指定すれば見られる。
     */
    public function test_rejected_labor_does_not_appear_on_the_labor_record_screen(): void
    {
        Mail::fake();
        $worker = Staff::factory()->create(['position_weight' => 1]);
        $manager = $this->manager();
        $this->actingAs($manager)->post(route('purchasing.input.store'), $this->laborPayload($worker));
        $report = DailyReport::sole();

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '注番が違います',
        ]);

        $this->actingAs($manager)->get(route('labor-records.index'))
            ->assertOk()
            ->assertViewHas('records', fn ($records) => $records->isEmpty());

        // 作業日報の確認画面では、日付を指定すればその日の日報として出てくる。
        $this->actingAs($this->reviewer())->get(route('daily-reports.review.index', ['date' => '2026-08-05']))
            ->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->contains('id', $report->id));
    }

    /** 差し戻しは代理提出と同じく、日報単位で効く。 */
    public function test_rejecting_the_report_notifies_the_worker(): void
    {
        Mail::fake();
        $worker = Staff::factory()->create(['position_weight' => 1]);
        $this->actingAs($this->manager())->post(route('purchasing.input.store'), $this->laborPayload($worker));
        $report = DailyReport::sole();

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '注番が違います',
        ])->assertRedirect();

        Mail::assertSent(DailyReportRejectedMail::class, fn ($mail) => $mail->hasTo($worker->email));
    }
}
