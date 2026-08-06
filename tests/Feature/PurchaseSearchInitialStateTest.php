<?php

namespace Tests\Feature;

use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 仕入管理の検索は、画面を開いた直後(クエリ文字列が空)は何も出さない。
 * 24万件の一覧をいきなり見せても使い道がないため。検索ボタンや物件表示を押せば、
 * 条件が未入力でも全件が対象になる。
 */
class PurchaseSearchInitialStateTest extends TestCase
{
    use RefreshDatabase;

    private function seedDetail(): Staff
    {
        PurchaseDetail::create(['item_code' => 'INIT1-N01', 'item_name' => '初期表示テスト部品', 'is_provisional' => false]);

        return Staff::factory()->procurementManager()->create();
    }

    public function test_nothing_is_listed_before_searching(): void
    {
        $manager = $this->seedDetail();

        $this->actingAs($manager)->get(route('purchasing.index'))
            ->assertOk()
            ->assertViewHas('searched', false)
            ->assertDontSee('初期表示テスト部品')
            ->assertSee('条件を入力して検索してください');
    }

    public function test_searching_with_every_field_empty_covers_everything(): void
    {
        $manager = $this->seedDetail();

        // 検索ボタンは空の項目もクエリ文字列に載せて送信する
        $this->actingAs($manager)->get(route('purchasing.index', ['item_code' => '', 'item_name' => '']))
            ->assertOk()
            ->assertViewHas('searched', true)
            ->assertSee('初期表示テスト部品');
    }

    public function test_the_project_display_button_alone_also_searches(): void
    {
        $manager = $this->seedDetail();

        $this->actingAs($manager)->get(route('purchasing.index', ['show_projects' => 1]))
            ->assertOk()
            ->assertViewHas('searched', true)
            ->assertSee('初期表示テスト部品')
            ->assertSee('物件（受注ヘッダ）');
    }

    public function test_links_from_other_screens_still_show_results(): void
    {
        $manager = $this->seedDetail();

        // 原価計算などから注番付きで飛んできた場合
        $this->actingAs($manager)->get(route('purchasing.index', ['item_code' => 'INIT1-N01', 'item_code_match' => 'perfect']))
            ->assertOk()
            ->assertViewHas('searched', true)
            ->assertSee('初期表示テスト部品');
    }
}
