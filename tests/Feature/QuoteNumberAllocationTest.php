<?php

namespace Tests\Feature;

use App\Models\CustomerCode;
use App\Models\QuoteNumber;
use App\Models\Staff;
use App\Services\QuoteNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 見積番号の採番。案件の種類ごとに、見積単位・見積通番・補足区分のどこを新しく採るかが変わる。
 */
class QuoteNumberAllocationTest extends TestCase
{
    use RefreshDatabase;

    private QuoteNumberAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocator = app(QuoteNumberAllocator::class);

        CustomerCode::create(['code' => 'DH', 'company_name' => 'テスト客先']);

        foreach ([
            ['DH013-N01', '013', 'N', '01', null],
            ['DH013-N02', '013', 'N', '02', null],
            ['DH013-N01K01', '013', 'N', '01', 'K'],
            ['DH020-N01', '020', 'N', '01', null],
        ] as [$full, $unit, $type, $seq, $extra]) {
            QuoteNumber::create([
                'full_no' => $full, 'customer_code' => 'DH', 'unit_no' => $unit,
                'suffix' => substr($full, strpos($full, '-') + 1),
                'quote_type' => $type, 'quote_seq' => $seq, 'extra_code' => $extra, 'source' => 'legacy',
            ]);
        }
    }

    public function test_a_new_project_takes_the_next_unit_number_with_n01(): void
    {
        $result = $this->allocator->build('DH', 'new', null, null);

        // 老番は020なので021、通番は新しい単位なので01
        $this->assertSame('DH021-N01', $result['candidate']);
        $this->assertFalse($result['duplicate']);
    }

    public function test_a_fake_quote_uses_the_f_type(): void
    {
        $this->assertSame('DH021-F01', $this->allocator->build('DH', 'fake', null, null)['candidate']);
    }

    public function test_a_scope_change_reuses_the_unit_and_takes_the_next_quote_sequence(): void
    {
        // 013はN01・N02が取得済みなのでN03
        $this->assertSame('DH013-N03', $this->allocator->build('DH', 'scope_change', '013', null)['candidate']);
    }

    public function test_supplementary_types_hang_off_the_original_quote_number(): void
    {
        // 部品はB(修理のSではない)
        $this->assertSame('DH013-N01B01', $this->allocator->build('DH', 'parts', '013', '01')['candidate']);
        $this->assertSame('DH013-N01S01', $this->allocator->build('DH', 'repair', '013', '01')['candidate']);
        $this->assertSame('DH013-N01T01', $this->allocator->build('DH', 'additional', '013', '01')['candidate']);
        $this->assertSame('DH013-N01H01', $this->allocator->build('DH', 'change', '013', '01')['candidate']);

        // 改造はK01が取得済みなのでK02
        $this->assertSame('DH013-N01K02', $this->allocator->build('DH', 'remodel', '013', '01')['candidate']);
    }

    /**
     * H(変更)はNの後ろだけでなく、T/K/S/Bの後ろにも付く。
     */
    public function test_a_change_can_hang_off_a_supplementary_number(): void
    {
        $this->assertSame('DH013-N01K01H01', $this->allocator->build('DH', 'change', '013', 'N01K01')['candidate']);

        // 同じ元番号でもう1件採ると通番が進む
        QuoteNumber::create([
            'full_no' => 'DH013-N01K01H01', 'customer_code' => 'DH', 'unit_no' => '013',
            'suffix' => 'N01K01H01', 'quote_type' => 'N', 'quote_seq' => '01', 'extra_code' => 'K', 'source' => 'manage',
        ]);

        $this->assertSame('DH013-N01K01H02', $this->allocator->build('DH', 'change', '013', 'N01K01')['candidate']);
    }

    /**
     * 数字だけを入れた場合は通常番号の通番とみなしてNを補う。
     */
    /**
     * 表示・保存で quote_type + quote_seq から組み立て直すと、多段の途中(K10)が落ちて
     * TL061-N01K10H01 が TL061-N01H01 になっていた。ハイフン以降は必ず suffix を使う。
     */
    public function test_the_suffix_keeps_every_group_of_a_multi_level_base(): void
    {
        $result = $this->allocator->build('DH', 'change', '013', 'N01K10');

        $this->assertSame('DH013-N01K10H01', $result['candidate']);
        $this->assertSame('N01K10', $result['base_suffix']);
        $this->assertSame('N01K10H01', $result['suffix']);
    }

    public function test_a_taken_multi_level_number_is_stored_with_the_full_suffix(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $this->actingAs($staff)->post(route('quote-numbers.store'), [
            'customer_code' => 'DH', 'mode' => 'change', 'unit_no' => '013', 'base_no' => 'N01K10',
            'full_no' => 'DH013-N01K10H01',
            'project_name' => 'x', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $staff->id,
        ])->assertRedirect();

        $quote = QuoteNumber::where('full_no', 'DH013-N01K10H01')->sole();
        $this->assertSame('N01K10H01', $quote->suffix);
    }

    public function test_a_bare_sequence_is_treated_as_the_normal_quote_number(): void
    {
        $this->assertSame('DH013-N01H01', $this->allocator->build('DH', 'change', '013', '1')['candidate']);
    }

    /**
     * 改造・修理・部品は元注番がなければ新規案件(N)として採る。
     * 補足区分だと思い込みやすいので、画面側でも注釈を出している。
     */
    public function test_remodel_repair_and_parts_fall_back_to_a_new_project(): void
    {
        foreach (['remodel', 'repair', 'parts'] as $mode) {
            $result = $this->allocator->build('DH', $mode, null, null);

            $this->assertSame('DH021-N01', $result['candidate'], "{$mode} は元注番が無ければ新規採番になる");
            $this->assertTrue($result['fell_back_to_new']);
            $this->assertNull($result['extra_code']);
        }
    }

    /**
     * 追加請求(T)と変更(H)は必ず元注番にぶら下がるため、空のままでは採番しない。
     */
    public function test_additional_and_change_still_require_a_base_number(): void
    {
        foreach (['additional', 'change'] as $mode) {
            $result = $this->allocator->build('DH', $mode, null, null);

            $this->assertNull($result['candidate']);
            $this->assertSame(['通番', '元の見積番号'], $result['missing']);
        }
    }

    /**
     * 通番は 1 / 01 / 001 を同じものとして扱い、表示・保存は原則3桁に揃える。
     */
    public function test_the_unit_number_is_zero_padded_and_old_two_digit_units_are_matched(): void
    {
        QuoteNumber::create([
            'full_no' => 'DH15-N01', 'customer_code' => 'DH', 'unit_no' => '15',
            'suffix' => 'N01', 'quote_type' => 'N', 'quote_seq' => '01', 'source' => 'legacy',
        ]);

        // 過去分の「15」も通番015として同じ通番に数える
        foreach (['15', '015'] as $input) {
            $this->assertSame('DH015-N02', $this->allocator->build('DH', 'scope_change', $input, null)['candidate']);
        }

        // 表示は3桁で揃える
        $this->assertSame('DH015-N01', QuoteNumber::where('full_no', 'DH15-N01')->sole()->canonicalNo());
    }

    /**
     * 桁が揃っていない過去分の後ろに補足区分を採るとき、老番を取りこぼさないこと。
     */
    public function test_supplementary_sequences_see_units_written_with_different_digits(): void
    {
        QuoteNumber::create([
            'full_no' => 'DH20-N01K05', 'customer_code' => 'DH', 'unit_no' => '20',
            'suffix' => 'N01K05', 'quote_type' => 'N', 'quote_seq' => '01', 'extra_code' => 'K', 'source' => 'legacy',
        ]);

        // 既存はDH20表記だが、DH020として採ってもK06になる
        $this->assertSame('DH020-N01K06', $this->allocator->build('DH', 'remodel', '020', 'N01')['candidate']);
    }

    public function test_a_number_written_with_different_digits_counts_as_taken(): void
    {
        $this->assertTrue($this->allocator->isTaken('DH13-N01'));   // 台帳はDH013-N01
        $this->assertTrue($this->allocator->isTaken('DH013-N01'));
        $this->assertFalse($this->allocator->isTaken('DH013-N09'));
    }

    public function test_a_hand_edited_number_is_saved_with_a_three_digit_unit(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $this->actingAs($staff)->post(route('quote-numbers.store'), [
            'customer_code' => 'DH', 'mode' => 'new', 'full_no' => 'DH99-N05',
            'project_name' => 'x', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $staff->id,
        ])->assertRedirect();

        $this->assertNotNull(QuoteNumber::where('full_no', 'DH099-N05')->first());
    }

    public function test_the_lookup_matches_across_digit_widths(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);
        QuoteNumber::create([
            'full_no' => 'DH15-N01', 'customer_code' => 'DH', 'unit_no' => '15', 'suffix' => 'N01',
            'quote_type' => 'N', 'quote_seq' => '01', 'project_name' => '桁違いの物件', 'source' => 'legacy',
        ]);

        $this->actingAs($staff)->getJson(route('quote-numbers.lookup', ['no' => 'DH015-N01']))
            ->assertOk()
            ->assertJson(['found' => true, 'order_no' => 'DH015-N01', 'project_name' => '桁違いの物件']);
    }

    public function test_taking_a_number_records_it_with_the_selected_staff(): void
    {
        $sales = Staff::factory()->create(['role' => Staff::ROLE_SALES]);
        $manager = Staff::factory()->procurementManager()->create();

        // 経理資材担当が代行する場合は社内担当者を選んで取得する
        $this->actingAs($manager)->post(route('quote-numbers.store'), [
            'customer_code' => 'DH',
            'mode' => 'new',
            'full_no' => 'DH021-N01',
            'project_name' => 'テスト装置',
            'delivery_dest' => '第一工場',
            'customer_contact' => '客先太郎',
            'staff_id' => $sales->id,
        ])->assertRedirect();

        $quote = QuoteNumber::where('full_no', 'DH021-N01')->sole();
        $this->assertSame($sales->id, $quote->staff_id);
        $this->assertSame('manage', $quote->source);
        $this->assertSame('テスト装置', $quote->project_name);
    }

    public function test_the_candidate_can_be_edited_by_hand(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        // 候補は DH021-N01 だが、手入力で別の番号にして取得する
        $this->actingAs($staff)->post(route('quote-numbers.store'), [
            'customer_code' => 'DH', 'mode' => 'new', 'full_no' => 'DH099-N05K02',
            'project_name' => 'x', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $staff->id,
        ])->assertRedirect();

        $quote = QuoteNumber::where('full_no', 'DH099-N05K02')->sole();
        // 構成要素も入力値から取り直す
        $this->assertSame('099', $quote->unit_no);
        $this->assertSame('N05K02', $quote->suffix);
        $this->assertSame('05', $quote->quote_seq);
        $this->assertSame('K', $quote->extra_code);
        $this->assertNull(QuoteNumber::where('full_no', 'DH021-N01')->first());
    }

    public function test_a_hand_edited_number_that_already_exists_is_rejected(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $this->actingAs($staff)->post(route('quote-numbers.store'), [
            'customer_code' => 'DH', 'mode' => 'new', 'full_no' => 'DH013-N01',
            'project_name' => 'x', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $staff->id,
        ])->assertSessionHasErrors('full_no');

        $this->assertSame(1, QuoteNumber::where('full_no', 'DH013-N01')->count());
    }

    public function test_a_hand_edited_number_must_look_like_an_order_number(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $this->actingAs($staff)->post(route('quote-numbers.store'), [
            'customer_code' => 'DH', 'mode' => 'new', 'full_no' => 'でたらめ',
            'project_name' => 'x', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $staff->id,
        ])->assertSessionHasErrors('full_no');
    }

    public function test_taking_the_same_kind_twice_advances_the_sequence(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        foreach (['DH013-N03', 'DH013-N04'] as $expected) {
            $this->actingAs($staff)->post(route('quote-numbers.store'), [
                'customer_code' => 'DH', 'mode' => 'scope_change', 'unit_no' => '013', 'full_no' => $expected,
                'project_name' => 'x', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $staff->id,
            ])->assertRedirect();

            $this->assertNotNull(QuoteNumber::where('full_no', $expected)->first());
        }
    }

    public function test_the_lookup_endpoint_returns_the_project_details(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);
        QuoteNumber::where('full_no', 'DH013-N01')->update([
            'project_name' => '搬送装置', 'delivery_dest' => '第二工場', 'staff_id' => $staff->id,
        ]);

        $this->actingAs($staff)->getJson(route('quote-numbers.lookup', ['no' => 'DH013-N01']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'project_name' => '搬送装置',
                'recipient' => 'テスト客先',
                'delivery_dest' => '第二工場',
                'staff_id' => $staff->id,
            ]);

        $this->actingAs($staff)->getJson(route('quote-numbers.lookup', ['no' => 'XX999-N01']))
            ->assertOk()->assertJson(['found' => false]);
    }

    /**
     * 過去注番リストは補足区分(T/K/S/B/H)付きも含めて全件出す。
     * どの区分が何番まで使われているかを見ながら採番するため。
     */
    public function test_the_history_includes_supplementary_numbers(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $response = $this->actingAs($staff)->get(route('quote-numbers.index', [
            'customer_code' => 'DH', 'mode' => 'remodel',
        ]));

        $response->assertOk()
            ->assertSee('DH013-N01K01')   // 補足区分付き
            ->assertSee('DH013-N02');     // 通常
    }

    public function test_a_history_row_can_be_corrected(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);
        $quote = QuoteNumber::where('full_no', 'DH013-N01')->sole();

        $this->actingAs($staff)->put(route('quote-numbers.update', $quote), [
            'project_name' => '直した件名',
            'delivery_dest' => '直した納入先',
            'customer_contact' => '客先花子',
            'staff_id' => $staff->id,
            'remarks' => 'メモ',
        ])->assertRedirect();

        $quote->refresh();
        $this->assertSame('直した件名', $quote->project_name);
        $this->assertSame('直した納入先', $quote->delivery_dest);
        $this->assertSame($staff->id, $quote->staff_id);
        // 注番そのものは変わらない
        $this->assertSame('DH013-N01', $quote->full_no);
    }

    /**
     * 注番の検索ボタンは、注番管理の新規登録と受注登録の両方から使う。
     * どちらの画面を開ける人も検索APIを叩けること。
     */
    public function test_the_search_button_is_available_on_both_screens(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('order-numbers.create'))
            ->assertOk()->assertSee('検索')->assertSee(route('quote-numbers.lookup'), false);

        $this->actingAs($manager)->get(route('projects.create'))
            ->assertOk()->assertSee('検索')->assertSee(route('quote-numbers.lookup'), false);
    }

    /**
     * 取得ログはadministrator専用。誰がいつどの注番を採ったかを直近100件たどれる。
     */
    public function test_taking_a_number_records_a_log_visible_to_administrators(): void
    {
        $sales = Staff::factory()->create(['role' => Staff::ROLE_SALES]);
        $manager = Staff::factory()->procurementManager()->create();
        $admin = Staff::factory()->create(['is_administrator' => true]);

        // 経理資材担当が営業担当の代わりに採る
        $this->actingAs($manager)->post(route('quote-numbers.store'), [
            'customer_code' => 'DH', 'mode' => 'new', 'full_no' => 'DH021-N01',
            'project_name' => 'ログ確認装置', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $sales->id,
        ])->assertRedirect();

        $log = \App\Models\QuoteNumberLog::sole();
        $this->assertSame('taken', $log->action);
        $this->assertSame('DH021-N01', $log->full_no);
        $this->assertSame($manager->id, $log->staff_id);       // 操作者
        $this->assertSame($sales->id, $log->assigned_staff_id); // 社内担当者

        $this->actingAs($admin)->get(route('quote-numbers.logs'))
            ->assertOk()
            ->assertSee('DH021-N01')
            ->assertSee('ログ確認装置')
            ->assertSee($manager->name)
            ->assertSee($sales->name);
    }

    public function test_correcting_a_row_is_also_logged(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);
        $quote = QuoteNumber::where('full_no', 'DH013-N01')->sole();

        $this->actingAs($staff)->put(route('quote-numbers.update', $quote), [
            'project_name' => '直した件名',
        ])->assertRedirect();

        $log = \App\Models\QuoteNumberLog::sole();
        $this->assertSame('updated', $log->action);
        $this->assertSame('直した件名', $log->description);
    }

    /**
     * 誤って取得した注番を削除できる。削除しても取得ログは残る。
     */
    public function test_a_number_can_be_deleted_and_the_log_survives(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);
        $quote = QuoteNumber::where('full_no', 'DH013-N02')->sole();

        $this->actingAs($staff)->delete(route('quote-numbers.destroy', $quote))->assertRedirect();

        $this->assertNull(QuoteNumber::find($quote->id));

        $log = \App\Models\QuoteNumberLog::sole();
        $this->assertSame('deleted', $log->action);
        $this->assertSame('DH013-N02', $log->full_no);
        // 台帳の行が消えてもログは残る(参照はnullになる)
        $this->assertNull($log->fresh()->quote_number_id);
    }

    /**
     * 削除した番号は老番から外れるため、次の採番で再び使われる。
     */
    public function test_a_deleted_number_becomes_available_again(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $this->assertSame('DH013-N03', $this->allocator->build('DH', 'scope_change', '013', null)['candidate']);

        $this->actingAs($staff)->delete(route('quote-numbers.destroy', QuoteNumber::where('full_no', 'DH013-N02')->sole()));

        $this->assertSame('DH013-N02', $this->allocator->build('DH', 'scope_change', '013', null)['candidate']);
    }

    public function test_only_administrators_can_open_the_log(): void
    {
        $this->actingAs(Staff::factory()->procurementManager()->create())
            ->get(route('quote-numbers.logs'))->assertForbidden();

        $this->actingAs(Staff::factory()->create(['is_fund_manager' => true]))
            ->get(route('quote-numbers.logs'))->assertForbidden();
    }

    public function test_the_log_button_is_shown_only_to_administrators(): void
    {
        $this->actingAs(Staff::factory()->create(['is_administrator' => true]))
            ->get(route('quote-numbers.index'))->assertOk()->assertSee('取得ログ');

        $this->actingAs(Staff::factory()->procurementManager()->create())
            ->get(route('quote-numbers.index'))->assertOk()->assertDontSee('取得ログ');
    }

    /**
     * 採番は上長も使えるが、物件ボードは使えない。採番リンクを物件管理メニューの
     * 中に置いているため、ボードの権限でメニューごと隠すと上長から辿れなくなる。
     */
    public function test_a_supervisor_can_reach_the_allocation_screen_from_the_menu(): void
    {
        $supervisor = Staff::factory()->create(['role' => Staff::ROLE_GENERAL, 'is_supervisor' => true]);

        $this->assertTrue($supervisor->canAllocateQuoteNumber());
        $this->assertFalse($supervisor->canUseProjectBoard());

        $html = $this->actingAs($supervisor)->get(route('my-calendar.show'))->getContent();

        $this->assertStringContainsString('見積番号の採番', $html);
        // ボードは使えないのでリンクは出ない
        $this->assertStringNotContainsString('物件ボード', $html);
    }

    public function test_general_staff_cannot_allocate(): void
    {
        $this->actingAs(Staff::factory()->create())->get(route('quote-numbers.index'))->assertForbidden();
    }
}
