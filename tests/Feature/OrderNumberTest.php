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

    public function test_unused_order_number_can_be_deleted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);

        $this->actingAs($manager)->delete(route('order-numbers.destroy', $orderNumber))
            ->assertRedirect(route('order-numbers.index'));

        $this->assertDatabaseMissing('order_numbers', ['id' => $orderNumber->id]);
    }
}
