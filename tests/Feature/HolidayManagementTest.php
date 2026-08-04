<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_procurement_managers_can_manage_holidays(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('holidays.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('holidays.store'), [
            'date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY,
        ])->assertForbidden();
    }

    public function test_procurement_manager_can_register_a_holiday(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('holidays.store'), [
            'date' => '2026-08-13', 'name' => '夏季休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', [
            'name' => '夏季休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);
        $this->assertTrue(Holiday::whereDate('date', '2026-08-13')->exists());
    }

    public function test_holiday_requires_date_name_and_type(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('holidays.store'), []);

        $response->assertSessionHasErrors(['date', 'name', 'type']);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $response = $this->actingAs($manager)->post(route('holidays.store'), [
            'date' => '2026-01-01', 'name' => '別の名前', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);

        $response->assertSessionHasErrors('date');
        $this->assertSame(1, Holiday::whereDate('date', '2026-01-01')->count());
    }

    public function test_procurement_manager_can_update_a_holiday(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $holiday = Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $response = $this->actingAs($manager)->put(route('holidays.update', $holiday), [
            'date' => '2026-01-01', 'name' => '元旦', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => '元旦']);
    }

    public function test_updating_to_a_date_already_used_by_another_holiday_is_rejected(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);
        $other = Holiday::create(['date' => '2026-01-02', 'name' => '休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY]);

        $response = $this->actingAs($manager)->put(route('holidays.update', $other), [
            'date' => '2026-01-01', 'name' => '休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);

        $response->assertSessionHasErrors('date');
    }

    public function test_procurement_manager_can_delete_a_holiday(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $holiday = Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $this->actingAs($manager)->delete(route('holidays.destroy', $holiday))
            ->assertRedirect(route('holidays.index'));

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_index_lists_holidays_ordered_by_date(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Holiday::create(['date' => '2026-05-05', 'name' => 'こどもの日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);
        Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $response = $this->actingAs($manager)->get(route('holidays.index'));

        $response->assertSee('元日');
        $response->assertSee('こどもの日');
        $response->assertSeeInOrder(['元日', 'こどもの日']);
    }
}
