<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_procurement_manager_cannot_be_demoted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->put(route('staff.update', $manager), [
            'name' => $manager->name,
            'department' => $manager->department,
            'login_id' => $manager->login_id,
            'email' => $manager->email,
            // is_procurement_managerを送らない = falseとして送信
        ]);

        $response->assertSessionHasErrors('is_procurement_manager');
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
        ]);

        $response->assertRedirect(route('staff.index'));
        $this->assertFalse($manager->fresh()->is_procurement_manager);
        $this->assertTrue($otherManager->fresh()->is_procurement_manager);
    }
}
