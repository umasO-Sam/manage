<?php

namespace Tests\Feature;

use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_procurement_managers_can_manage_order_numbers(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('order-numbers.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('order-numbers.store'), ['code' => 'ZZ999-N99T99'])->assertForbidden();
    }

    public function test_procurement_manager_can_register_an_order_number(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('order-numbers.store'), [
            'code' => 'ZZ999-N99T99',
        ]);

        $response->assertRedirect(route('order-numbers.index'));
        $this->assertDatabaseHas('order_numbers', ['code' => 'ZZ999-N99T99', 'is_protected' => false]);
    }

    public function test_order_number_must_match_the_required_format(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('order-numbers.store'), [
            'code' => 'invalid!!',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_bypassing_format_check_allows_japanese_text(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('order-numbers.store'), [
            'code' => '〇〇工事現場支給品',
            'bypass_format_check' => '1',
        ]);

        $response->assertRedirect(route('order-numbers.index'));
        $this->assertDatabaseHas('order_numbers', ['code' => '〇〇工事現場支給品', 'is_protected' => false]);
    }

    public function test_order_number_format_allows_short_codes(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        // 「英数1〜8文字-英数2〜12文字」の緩和後の下限(1文字-2文字)を確認する。
        $response = $this->actingAs($manager)->post(route('order-numbers.store'), [
            'code' => 'A-11',
        ]);

        $response->assertRedirect(route('order-numbers.index'));
        $this->assertDatabaseHas('order_numbers', ['code' => 'A-11', 'is_protected' => false]);
    }

    public function test_order_number_format_rejects_prefix_over_eight_chars(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('order-numbers.store'), [
            'code' => 'ABCDEFGHI-N99T99',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_without_bypass_japanese_text_is_still_rejected(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('order-numbers.store'), [
            'code' => '〇〇工事現場支給品',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_default_order_numbers_exist_after_seeding(): void
    {
        $this->seed();

        $this->assertDatabaseHas('order_numbers', ['code' => '未定', 'is_protected' => true]);
        $this->assertDatabaseHas('order_numbers', ['code' => '社内', 'is_protected' => true]);
    }

    public function test_protected_order_number_cannot_be_deleted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $undecided = OrderNumber::create(['code' => '未定', 'is_protected' => true]);

        $this->actingAs($manager)->delete(route('order-numbers.destroy', $undecided))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('order_numbers', ['id' => $undecided->id]);
    }

    public function test_order_number_in_use_cannot_be_deleted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);

        $workflowType = WorkflowType::create([
            'slug' => 'purchase',
            'name' => '購入部品手配',
            'due_date_label' => '希望納期',
            'icon' => 'shopping-cart',
            'accent' => 'blue',
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '手配中', 'actor_label' => '手配担当者'],
                ['label' => '入荷', 'actor_label' => '受入担当者'],
            ],
            'retention_days' => 7,
        ]);

        $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $manager->id, 'current_stage' => 0,
        ]);

        $this->actingAs($manager)->delete(route('order-numbers.destroy', $orderNumber))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('order_numbers', ['id' => $orderNumber->id]);
    }

    public function test_freeform_order_number_is_labeled_in_the_list(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        OrderNumber::create(['code' => '〇〇工事現場支給品', 'is_protected' => false]);

        $response = $this->actingAs($manager)->get(route('order-numbers.index'));

        $response->assertSee('自由入力（形式チェック解除）');
    }

    public function test_the_list_is_sorted_by_code(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        // 登録順(id順)と昇順が食い違う並びで入れる
        foreach (['TL050-N01', 'AB001-N01', 'MK200-N02'] as $code) {
            OrderNumber::create(['code' => $code, 'is_protected' => false]);
        }

        $this->actingAs($manager)->get(route('order-numbers.index'))
            ->assertOk()
            ->assertSeeInOrder(['AB001-N01', 'MK200-N02', 'TL050-N01']);
    }

    /**
     * 「プルダウンに表示」。注番が増えて選択肢が長くなるため、終わった案件を外せる。
     * 外しても登録済みのレコードが持つ注番はそのまま残す。
     */
    public function test_order_numbers_hidden_from_the_dropdown_are_not_offered_as_choices(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $hidden = OrderNumber::create(['code' => 'ZZ001-N01', 'is_protected' => false]);
        $shown = OrderNumber::create(['code' => 'ZZ002-N01', 'is_protected' => false]);

        $this->actingAs($manager)->put(route('order-numbers.update', $hidden), ['show_in_dropdown' => '0'])
            ->assertRedirect(route('order-numbers.index'));
        $this->assertFalse($hidden->fresh()->show_in_dropdown);

        // 作業日報・休暇申請の注番プルダウン
        foreach (['daily-reports.show', 'leave-requests.create'] as $routeName) {
            $this->actingAs($manager)->get(route($routeName))
                ->assertOk()->assertDontSee($hidden->code)->assertSee($shown->code);
        }

        // 注番管理の一覧からは消さない(消すと設定を戻せなくなる)
        $this->actingAs($manager)->get(route('order-numbers.index'))->assertSee($hidden->code);
    }

    public function test_a_card_keeps_its_order_number_as_a_choice_even_when_hidden(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $workflowType = WorkflowType::create([
            'slug' => 'purchase', 'name' => '購入手配', 'due_date_label' => '希望納期',
            'icon' => 'shopping-cart', 'accent' => 'blue',
            'stage_definition' => [['label' => '新規依頼', 'actor_label' => '依頼者']],
            'retention_days' => 7,
        ]);
        $hidden = OrderNumber::create(['code' => 'ZZ003-N01', 'is_protected' => false, 'show_in_dropdown' => false]);
        $card = $workflowType->cards()->create([
            'order_number_id' => $hidden->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(),
            'created_by' => $manager->id, 'current_stage' => 0,
        ]);

        $this->actingAs($manager)->get(route('cards.edit', $card))
            ->assertOk()->assertSee($hidden->code);

        // 新規作成の選択肢には出さない
        $this->actingAs($manager)->get(route('cards.create', $workflowType))
            ->assertOk()->assertDontSee($hidden->code);
    }

    public function test_unused_order_number_can_be_deleted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);

        $this->actingAs($manager)->delete(route('order-numbers.destroy', $orderNumber))
            ->assertRedirect(route('order-numbers.index'));

        $this->assertDatabaseMissing('order_numbers', ['id' => $orderNumber->id]);
    }
}
