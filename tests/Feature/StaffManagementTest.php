<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseWorkflow(): WorkflowType
    {
        return WorkflowType::create([
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
    }

    public function test_last_procurement_manager_cannot_be_demoted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->put(route('staff.update', $manager), [
            'name' => $manager->name,
            'department' => $manager->department,
            'login_id' => $manager->login_id,
            'email' => $manager->email,
            'role' => Staff::ROLE_GENERAL,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertTrue($manager->fresh()->is_procurement_manager);
    }

    public function test_procurement_manager_can_be_demoted_when_another_manager_remains(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $otherManager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->put(route('staff.update', $manager), [
            'name' => $manager->name,
            'department' => $manager->department,
            'login_id' => $manager->login_id,
            'email' => $manager->email,
            'role' => Staff::ROLE_GENERAL,
        ]);

        $response->assertRedirect(route('staff.index'));
        $this->assertFalse($manager->fresh()->is_procurement_manager);
        $this->assertTrue($otherManager->fresh()->is_procurement_manager);
    }

    public function test_sales_role_can_view_search_but_not_data_entry(): void
    {
        $sales = Staff::factory()->sales()->create();

        $this->actingAs($sales)->get(route('purchasing.index'))->assertOk();
        $this->actingAs($sales)->get(route('purchasing.cost.index'))->assertOk();
        $this->actingAs($sales)->get(route('purchasing.input'))->assertForbidden();
        $this->actingAs($sales)->get(route('staff.index'))->assertForbidden();
    }

    public function test_general_role_cannot_view_purchasing_at_all(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $general = Staff::factory()->create();

        $this->actingAs($general)->get(route('purchasing.index'))->assertForbidden();
        $this->actingAs($general)->get(route('purchasing.cost.index'))->assertForbidden();
        $this->actingAs($general)->get(route('cards.index', $workflowType))->assertOk();
        $this->actingAs($general)->get(route('archive.index'))->assertOk();
    }
}
