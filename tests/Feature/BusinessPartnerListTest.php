<?php

namespace Tests\Feature;

use App\Models\BusinessPartner;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 取引先一覧の表形式編集。社数が多いため、1件ずつのフォームではなく
 * 直接編集(まとめて保存)とタブ区切りの貼り付け一括登録で扱う。
 */
class BusinessPartnerListTest extends TestCase
{
    use RefreshDatabase;

    private function fundManager(): Staff
    {
        return Staff::factory()->create(['is_fund_manager' => true]);
    }

    public function test_only_fund_managers_can_open_the_list(): void
    {
        $this->actingAs($this->fundManager())->get(route('business-partners.index'))->assertOk();
        $this->actingAs(Staff::factory()->procurementManager()->create())->get(route('business-partners.index'))->assertForbidden();
    }

    public function test_direct_edit_saves_several_partners_at_once(): void
    {
        $first = BusinessPartner::create(['name' => 'あ社', 'is_provisional' => false]);
        $second = BusinessPartner::create(['name' => 'い社', 'is_provisional' => false]);

        $this->actingAs($this->fundManager())->put(route('business-partners.bulk-update'), [
            'updates' => [
                $first->id => ['name' => 'あ社', 'tel' => '052-000-0001', 'related_order_nos' => 'DH013-N01 Q001-N02'],
                $second->id => ['name' => 'い社（改称）', 'display_order' => '5', 'address' => "愛知県\n名古屋市"],
            ],
        ])->assertRedirect();

        $first->refresh();
        $second->refresh();
        $this->assertSame('052-000-0001', $first->tel);
        $this->assertSame(['DH013-N01', 'Q001-N02'], $first->relatedOrderNoList());
        $this->assertSame('い社（改称）', $second->name);
        $this->assertSame(5, $second->display_order);
        $this->assertSame("愛知県\n名古屋市", $second->address);
    }

    /**
     * 1行でも直せない内容があれば何も保存しない。半端に保存されると
     * どこまで直ったのかが分からなくなるため。
     */
    public function test_direct_edit_saves_nothing_when_one_row_is_invalid(): void
    {
        $first = BusinessPartner::create(['name' => 'あ社', 'is_provisional' => false]);
        $second = BusinessPartner::create(['name' => 'い社', 'is_provisional' => false]);

        $this->actingAs($this->fundManager())->put(route('business-partners.bulk-update'), [
            'updates' => [
                $first->id => ['name' => 'あ社', 'tel' => '052-000-0001'],
                // 既にある名前に変えようとする(受注先名は一意)
                $second->id => ['name' => 'あ社'],
            ],
        ])->assertSessionHasErrors();

        $this->assertNull($first->fresh()->tel);
        $this->assertSame('い社', $second->fresh()->name);
    }

    public function test_pasted_rows_are_shown_for_confirmation_before_they_are_saved(): void
    {
        $line = implode("\t", ['あい', '㈱テスト', '460-0001', '名古屋市', '052-1', '052-2', '末締め', '10', '', '3', '備考', '', 'DH013-N01']);

        $this->actingAs($this->fundManager())
            ->post(route('business-partners.bulk-paste'), ['paste_data' => $line])
            ->assertOk()
            ->assertSee('㈱テスト');

        $this->assertSame(0, BusinessPartner::count(), '確認画面の時点ではまだ登録しない');
    }

    public function test_pasted_rows_are_saved_after_confirmation(): void
    {
        $lines = implode("\n", [
            implode("\t", ['あい', '㈱テスト', '460-0001', '名古屋市', '052-1', '052-2', '末締め', '10', '', '3', '備考', '', 'DH013-N01']),
            implode("\t", ['かき', '化学㈱', '', '', '', '', '', '', '', '', '', '', '']),
        ]);

        $this->actingAs($this->fundManager())
            ->post(route('business-partners.bulk-paste'), ['paste_data' => $lines, 'confirmed' => '1'])
            ->assertRedirect(route('business-partners.index'));

        $this->assertSame(2, BusinessPartner::count());

        $partner = BusinessPartner::where('name', '㈱テスト')->first();
        $this->assertSame('あい', $partner->kana_group);
        $this->assertSame('末締め', $partner->handling_method);
        $this->assertSame(3, $partner->display_order);
        $this->assertSame(['DH013-N01'], $partner->relatedOrderNoList());
        // 取引実績のある既存先として入れるので、取引条件調整中にはしない。
        $this->assertFalse($partner->is_provisional);
    }

    public function test_pasting_an_existing_partner_saves_nothing(): void
    {
        BusinessPartner::create(['name' => '㈱テスト', 'is_provisional' => false]);

        $lines = implode("\n", [
            implode("\t", ['あい', '㈱テスト']),
            implode("\t", ['かき', '化学㈱']),
        ]);

        $this->actingAs($this->fundManager())
            ->post(route('business-partners.bulk-paste'), ['paste_data' => $lines, 'confirmed' => '1'])
            ->assertSessionHasErrors('paste_data');

        $this->assertSame(1, BusinessPartner::count());
    }

    /** Excelから見出しごとコピーされた場合、その行は取り込まない。 */
    public function test_a_pasted_header_row_is_skipped(): void
    {
        $lines = implode("\n", [
            implode("\t", ['50音', '取引先', '郵便番号']),
            implode("\t", ['あい', '㈱テスト']),
        ]);

        $this->actingAs($this->fundManager())
            ->post(route('business-partners.bulk-paste'), ['paste_data' => $lines, 'confirmed' => '1'])
            ->assertRedirect();

        $this->assertSame(['㈱テスト'], BusinessPartner::pluck('name')->all());
    }
}
