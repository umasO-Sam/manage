<?php

namespace Tests\Feature;

use App\Models\OrderNumber;
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
        $this->actingAs($sales)->get(route('purchasing.labor.index'))->assertOk();
        $this->actingAs($sales)->get(route('purchasing.input'))->assertForbidden();
        $this->actingAs($sales)->get(route('staff.index'))->assertForbidden();

        // ナビゲーションの「仕入管理」メニューにも人工計算リンクが表示される必要がある
        // (procurement.manager限定の項目と誤って同じ条件分岐に入れないよう確認する)。
        $this->actingAs($sales)->get(route('purchasing.index'))->assertSee('人工計算');
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

    public function test_manager_can_set_sid_when_creating_a_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('staff.store'), [
            'name' => '新入社員',
            'department' => '製造部',
            'sid' => 50,
            'login_id' => 'new-staff',
            'email' => 'new-staff@example.com',
            'role' => Staff::ROLE_GENERAL,
            'password' => 'Abcdefgh123456789012',
        ]);

        $response->assertRedirect(route('staff.index'));
        $this->assertDatabaseHas('staff', ['login_id' => 'new-staff', 'sid' => 50]);
    }

    public function test_ordered_for_roster_sorts_by_department_order_then_display_order(): void
    {
        Staff::factory()->create(['name' => '製造二郎', 'department' => '製造', 'display_order' => 2]);
        Staff::factory()->create(['name' => '製造太郎', 'department' => '製造', 'display_order' => 1]);
        Staff::factory()->create(['name' => '営業花子', 'department' => '営業', 'display_order' => 1]);
        Staff::factory()->create(['name' => '謎部署太郎', 'department' => '謎の部署', 'display_order' => 0]);
        Staff::factory()->create(['name' => '役員太郎', 'department' => '役員', 'display_order' => 1]);

        $names = Staff::orderedForRoster()->pluck('name')->all();

        $this->assertSame(['役員太郎', '営業花子', '製造太郎', '製造二郎', '謎部署太郎'], $names);
    }

    public function test_manager_can_set_display_order_when_creating_a_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->post(route('staff.store'), [
            'name' => '新入社員', 'department' => '機械製造', 'display_order' => 5,
            'login_id' => 'ordered-staff', 'email' => 'ordered-staff@example.com',
            'role' => Staff::ROLE_GENERAL, 'password' => 'Abcdefgh123456789012',
        ]);

        $this->assertDatabaseHas('staff', ['login_id' => 'ordered-staff', 'display_order' => 5]);
    }

    public function test_new_staff_defaults_is_supervisor_to_null(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->post(route('staff.store'), [
            'name' => '新入社員',
            'department' => '製造部',
            'login_id' => 'new-staff2',
            'email' => 'new-staff2@example.com',
            'role' => Staff::ROLE_GENERAL,
            'password' => 'Abcdefgh123456789012',
        ]);

        // チェックボックス未送信時はfalseになる(新規作成フォームで明示的に選ばなかった場合)。
        $created = Staff::where('login_id', 'new-staff2')->first();
        $this->assertFalse((bool) $created->is_supervisor);
    }

    public function test_manager_can_set_and_unset_supervisor_flag(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create(['is_supervisor' => null]);

        $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
            'is_supervisor' => '1',
        ]);

        $this->assertTrue((bool) $target->fresh()->is_supervisor);

        $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
        ]);

        $this->assertFalse((bool) $target->fresh()->is_supervisor);
    }

    public function test_manager_can_set_paid_leave_grants(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create();

        $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
            'paid_leave_granted_current_year' => '12.5',
            'paid_leave_granted_last_year' => '3',
        ])->assertRedirect(route('staff.index'));

        $fresh = $target->fresh();
        $this->assertSame(12.5, (float) $fresh->paid_leave_granted_current_year);
        $this->assertSame(3.0, (float) $fresh->paid_leave_granted_last_year);
    }

    public function test_manager_can_update_and_clear_sid(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create(['sid' => 12]);

        $response = $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'sid' => 99,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
        ]);

        $response->assertRedirect(route('staff.index'));
        $this->assertSame(99, $target->fresh()->sid);

        $response = $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'sid' => '',
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
        ]);

        $response->assertRedirect(route('staff.index'));
        $this->assertNull($target->fresh()->sid);
    }

    public function test_sid_must_be_unique_across_staff(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Staff::factory()->create(['sid' => 12]);
        $target = Staff::factory()->create(['sid' => 13]);

        $response = $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'sid' => 12,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
        ]);

        $response->assertSessionHasErrors('sid');
        $this->assertSame(13, $target->fresh()->sid);
    }

    public function test_manager_can_bulk_update_staff_from_table_view(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create(['name' => '旧名前', 'sid' => null]);

        $response = $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [
                $target->id => [
                    'name' => '新しい名前',
                    'department' => '新しい部署',
                    'sid' => 21,
                    'login_id' => $target->login_id,
                    'email' => $target->email,
                    'role' => Staff::ROLE_SALES,
                ],
            ],
        ]);

        $response->assertRedirect(route('staff.index'));
        $target->refresh();
        $this->assertSame('新しい名前', $target->name);
        $this->assertSame('新しい部署', $target->department);
        $this->assertSame(21, $target->sid);
        $this->assertSame(Staff::ROLE_SALES, $target->role);
    }

    public function test_bulk_update_does_not_touch_password(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create();
        $originalPassword = $target->password;

        $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [
                $target->id => [
                    'name' => $target->name,
                    'department' => $target->department,
                    'login_id' => $target->login_id,
                    'email' => $target->email,
                    'role' => $target->role,
                ],
            ],
        ]);

        $this->assertSame($originalPassword, $target->fresh()->password);
    }

    public function test_bulk_update_allows_swapping_manager_role_between_two_rows_in_one_request(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $promotee = Staff::factory()->create(['role' => Staff::ROLE_GENERAL]);

        $response = $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [
                $manager->id => [
                    'name' => $manager->name,
                    'department' => $manager->department,
                    'login_id' => $manager->login_id,
                    'email' => $manager->email,
                    'role' => Staff::ROLE_GENERAL,
                ],
                $promotee->id => [
                    'name' => $promotee->name,
                    'department' => $promotee->department,
                    'login_id' => $promotee->login_id,
                    'email' => $promotee->email,
                    'role' => Staff::ROLE_PROCUREMENT_MANAGER,
                ],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertFalse($manager->fresh()->is_procurement_manager);
        $this->assertTrue($promotee->fresh()->is_procurement_manager);
    }

    public function test_bulk_update_rejects_when_no_manager_would_remain_and_saves_nothing(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $other = Staff::factory()->create(['name' => '変更前']);

        $response = $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [
                $manager->id => [
                    'name' => $manager->name,
                    'department' => $manager->department,
                    'login_id' => $manager->login_id,
                    'email' => $manager->email,
                    'role' => Staff::ROLE_GENERAL,
                ],
                $other->id => [
                    'name' => '変更後',
                    'department' => $other->department,
                    'login_id' => $other->login_id,
                    'email' => $other->email,
                    'role' => Staff::ROLE_GENERAL,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('bulk_update');
        $this->assertTrue($manager->fresh()->is_procurement_manager);
        $this->assertSame('変更前', $other->fresh()->name);
    }

    public function test_bulk_update_rejects_duplicate_login_id_and_saves_nothing(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $existing = Staff::factory()->create(['login_id' => 'taken-id']);
        $target = Staff::factory()->create(['name' => '変更前']);

        $response = $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [
                $target->id => [
                    'name' => '変更後',
                    'department' => $target->department,
                    'login_id' => 'taken-id',
                    'email' => $target->email,
                    'role' => $target->role,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('bulk_update');
        $this->assertSame('変更前', $target->fresh()->name);
        $this->assertSame('taken-id', $existing->fresh()->login_id);
    }

    public function test_bulk_update_requires_procurement_manager(): void
    {
        $staff = Staff::factory()->create();
        $target = Staff::factory()->create();

        $this->actingAs($staff)->post(route('staff.bulk-update'), [
            'updates' => [
                $target->id => [
                    'name' => '変更後',
                    'department' => $target->department,
                    'login_id' => $target->login_id,
                    'email' => $target->email,
                    'role' => $target->role,
                ],
            ],
        ])->assertForbidden();
    }

    public function test_manager_can_delete_a_staff_with_no_history(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create();

        $response = $this->actingAs($manager)->delete(route('staff.destroy', $target));

        $response->assertRedirect(route('staff.index'));
        $this->assertModelMissing($target);
    }

    public function test_staff_cannot_delete_themselves(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->delete(route('staff.destroy', $manager));

        $response->assertSessionHasErrors('delete');
        $this->assertModelExists($manager);
    }

    public function test_staff_with_card_history_cannot_be_deleted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $requester = Staff::factory()->create();
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);

        $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'model_number' => 'ABC-123', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date_type' => 'specific', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $response = $this->actingAs($manager)->delete(route('staff.destroy', $requester));

        $response->assertSessionHasErrors('delete');
        $this->assertModelExists($requester);
    }
}
