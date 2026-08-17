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
     * 見積台帳の大半は「Q511」のようにハイフン以降を持たない旧形式で、引用しても
     * 元番号が空のまま渡る。そのままでは新規案件に倒れてしまうため、N01があった
     * ものとみなして補足区分を採る。補ったことは画面側で注意喚起する。
     */
    public function test_quoting_an_old_format_number_assumes_n01(): void
    {
        QuoteNumber::create([
            'full_no' => 'DH031', 'customer_code' => 'DH', 'unit_no' => '031',
            'suffix' => null, 'quote_type' => null, 'quote_seq' => null, 'extra_code' => null, 'source' => 'legacy',
        ]);

        // 引用すると見積単位だけが入り、元番号は空で渡る。
        $result = $this->allocator->build('DH', 'parts', '031', null);

        $this->assertSame('DH031-N01B01', $result['candidate']);
        $this->assertTrue($result['quoted_old_format']);
        $this->assertFalse($result['fell_back_to_new']);
        $this->assertSame('N01', $result['base_suffix']);
        $this->assertSame('B', $result['extra_code']);
    }

    /**
     * 旧形式は見積単位に区分が混ざる(「031B」)。先頭の数字を見積単位として扱う。
     */
    public function test_quoting_an_old_format_unit_with_a_trailing_code(): void
    {
        $result = $this->allocator->build('DH', 'remodel', '031B', null);

        $this->assertSame('DH031-N01K01', $result['candidate']);
        $this->assertTrue($result['quoted_old_format']);
    }

    /**
     * 元注番を引用していない(見積単位も空)場合は、これまでどおり新規案件に倒す。
     * 旧形式の救済と取り違えないこと。
     */
    public function test_without_quoting_the_fall_back_to_new_still_applies(): void
    {
        $result = $this->allocator->build('DH', 'parts', null, null);

        $this->assertSame('DH021-N01', $result['candidate']);
        $this->assertTrue($result['fell_back_to_new']);
        $this->assertFalse($result['quoted_old_format']);
    }

    /**
     * 追加請求(T)と変更(H)も、引用さえしていればN01を補って採番できる。
     */
    public function test_additional_and_change_also_assume_n01_when_quoted(): void
    {
        $this->assertSame('DH031-N01T01', $this->allocator->build('DH', 'additional', '031', null)['candidate']);
        $this->assertSame('DH031-N01H01', $this->allocator->build('DH', 'change', '031', null)['candidate']);
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

    /** 取得する注番の英字は必ず大文字にする(注番管理・物件管理と突き合わせるため)。 */
    public function test_a_hand_edited_number_is_stored_in_uppercase(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $this->actingAs($staff)->post(route('quote-numbers.store'), [
            'customer_code' => 'dh', 'mode' => 'new', 'full_no' => 'dh099-n05k02',
            'project_name' => 'x', 'delivery_dest' => 'y', 'customer_contact' => 'z', 'staff_id' => $staff->id,
        ])->assertRedirect();

        $quote = QuoteNumber::where('full_no', 'DH099-N05K02')->sole();
        $this->assertSame('DH', $quote->customer_code);
        $this->assertSame('N05K02', $quote->suffix);
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

    /**
     * 客先の注番は900件を超えることがあるため、「DH013」のように通番まで入れて
     * 検索すると過去注番リストを絞り込む。客先番号だけならこれまでどおり全件。
     */
    public function test_searching_with_a_unit_number_filters_the_history(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('quote-numbers.index', ['customer_code' => 'DH013']))
            ->assertOk()
            ->assertViewHas('customerCode', 'DH')
            ->assertViewHas('searchTerm', 'DH013')
            ->assertViewHas('history', fn ($history) => $history->pluck('full_no')->sort()->values()->all()
                === ['DH013-N01', 'DH013-N01K01', 'DH013-N02'])
            ->assertSee('で絞り込み中');

        // 客先番号だけなら絞り込まない(setUpで作った4件が全部出る)。
        $this->actingAs($manager)->get(route('quote-numbers.index', ['customer_code' => 'DH']))
            ->assertOk()
            ->assertViewHas('history', fn ($history) => $history->count() === 4)
            ->assertDontSee('で絞り込み中');
    }

    /**
     * 絞り込んでいても採番は客先番号で行う。検索欄に「DH013」と入れたまま
     * 新規案件を選んでも、客先DHの老番+1で採番できること。
     */
    public function test_filtering_does_not_break_the_allocation(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('quote-numbers.index', ['customer_code' => 'DH013', 'mode' => 'new']))
            ->assertOk()
            ->assertViewHas('allocation', fn (array $a) => $a['candidate'] === 'DH021-N01');
    }

    /**
     * 本番の TL091 と同じ形。通常番号 N01 のほかに、N02 にぶら下がる部品提供が
     * B01〜B04 まであり、N02 単体の行は台帳に無い。
     */
    private function seedUnitWithSeveralBaseNumbers(): void
    {
        CustomerCode::create(['code' => 'TL', 'company_name' => 'テスト装置客先']);

        foreach ([
            ['TL091-N01', 'N01', 'N', '01', null],
            ['TL091-N01T1', 'N01T1', 'N', '01', 'T'],
            ['TL091-N02B01', 'N02B01', 'N', '02', 'B'],
            ['TL091-N02B02', 'N02B02', 'N', '02', 'B'],
            ['TL091-N02B03', 'N02B03', 'N', '02', 'B'],
            ['TL091-N02B04', 'N02B04', 'N', '02', 'B'],
        ] as [$full, $suffix, $type, $seq, $extra]) {
            QuoteNumber::create([
                'full_no' => $full, 'customer_code' => 'TL', 'unit_no' => '091', 'suffix' => $suffix,
                'quote_type' => $type, 'quote_seq' => $seq, 'extra_code' => $extra, 'source' => 'legacy',
            ]);
        }

        // 客先の老番。ここに倒れてしまうと別の装置の番号を発番することになる。
        QuoteNumber::create([
            'full_no' => 'TL147-N01', 'customer_code' => 'TL', 'unit_no' => '147', 'suffix' => 'N01',
            'quote_type' => 'N', 'quote_seq' => '01', 'extra_code' => null, 'source' => 'legacy',
        ]);
    }

    /**
     * 見積単位に元番号が複数あるときは、どれにぶら下げるかを決められないので聞き返す。
     * 勝手にどちらかを選ぶと、別の装置の部品として採番してしまう。
     */
    public function test_a_unit_with_several_base_numbers_asks_which_one_to_hang_off(): void
    {
        $this->seedUnitWithSeveralBaseNumbers();

        $result = $this->allocator->build('TL', 'parts', '091', null);

        $this->assertNull($result['candidate']);
        $this->assertSame(['元の見積番号'], $result['missing']);
        // 実在する注番と、その先頭グループ(台帳に単体の行が無い N02 を含む)。
        $this->assertSame(
            ['N01', 'N01T1', 'N02', 'N02B01', 'N02B02', 'N02B03', 'N02B04'],
            $result['base_choices']->all()
        );
    }

    /**
     * 元番号を選べば、その番号にぶら下がる補足区分の老番+1で採番する。
     */
    public function test_choosing_the_base_number_hangs_the_parts_off_it(): void
    {
        $this->seedUnitWithSeveralBaseNumbers();

        // N01 に部品提供はまだ無いので B01。
        $this->assertSame('TL091-N01B01', $this->allocator->build('TL', 'parts', '091', 'N01')['candidate']);
        // N02 は B04 まで使われているので B05。
        $this->assertSame('TL091-N02B05', $this->allocator->build('TL', 'parts', '091', 'N02')['candidate']);
        // 規約どおり B は改造(K)の後ろにも付く。
        $this->assertSame('TL091-N01K01B01', $this->allocator->build('TL', 'parts', '091', 'N01K01')['candidate']);
    }

    /**
     * 元番号が1つしか無ければ迷いようがないので自動で決める。
     */
    public function test_a_unit_with_a_single_base_number_selects_it_automatically(): void
    {
        $result = $this->allocator->build('DH', 'parts', '020', null);

        $this->assertSame('DH020-N01B01', $result['candidate']);
        $this->assertTrue($result['base_auto_selected']);
        $this->assertFalse($result['fell_back_to_new']);
        $this->assertFalse($result['quoted_old_format']);
    }

    /**
     * 見積単位を指定しているときは新規案件に倒さない。特定の装置を指しておきながら
     * 客先の老番+1(別の装置)を発番し、気づかず取得してしまう事故を防ぐ。
     */
    public function test_specifying_a_unit_does_not_fall_back_to_a_new_project(): void
    {
        $this->seedUnitWithSeveralBaseNumbers();

        $result = $this->allocator->build('TL', 'parts', '091', null);

        $this->assertFalse($result['fell_back_to_new']);
        $this->assertNotSame('TL148-N01', $result['candidate']);

        // 客先番号だけのときは従来どおり新規案件として採番する。
        $withoutUnit = $this->allocator->build('TL', 'parts', null, null);
        $this->assertSame('TL148-N01', $withoutUnit['candidate']);
        $this->assertTrue($withoutUnit['fell_back_to_new']);
    }

    /**
     * 「TL091」のように通番まで入れて検索したら、その通番を採番にも使う。
     * 客先番号だけを見て老番+1に倒れると、別の装置の番号を発番してしまう。
     */
    public function test_the_searched_unit_number_is_used_for_the_allocation(): void
    {
        $this->seedUnitWithSeveralBaseNumbers();
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('quote-numbers.index', ['customer_code' => 'TL091', 'mode' => 'parts']))
            ->assertOk()
            ->assertViewHas('unitNo', '091')
            ->assertViewHas('allocation', fn (array $a) => $a['candidate'] === null
                && $a['missing'] === ['元の見積番号']
                && $a['fell_back_to_new'] === false);

        // 元番号を選べば候補が決まる。
        $this->actingAs($manager)->get(route('quote-numbers.index', [
            'customer_code' => 'TL091', 'mode' => 'parts', 'base_no' => 'N01',
        ]))->assertOk()->assertViewHas('allocation', fn (array $a) => $a['candidate'] === 'TL091-N01B01');
    }

    /**
     * 案件種別を選んでも過去注番リストの絞り込みを保つ。種別のフォームが客先番号だけを
     * 送っていると、選んだ瞬間に絞り込みが解けて900件の一覧に戻ってしまう。
     */
    public function test_choosing_a_mode_keeps_the_history_filter(): void
    {
        $this->seedUnitWithSeveralBaseNumbers();
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('quote-numbers.index', ['customer_code' => 'TL091', 'mode' => 'parts']))
            ->assertOk()
            ->assertViewHas('searchTerm', 'TL091')
            ->assertViewHas('history', fn ($history) => $history->count() === 6
                && $history->every(fn ($q) => str_starts_with($q->full_no, 'TL091')))
            ->assertSee('で絞り込み中');
    }
}
