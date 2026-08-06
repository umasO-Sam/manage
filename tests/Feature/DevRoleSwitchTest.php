<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 開発環境専用の権限切替。各権限での画面の見え方を確認するためのテスト機能で、
 * 本番には存在しない。
 */
class DevRoleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_screen_switches_the_current_users_role_and_flags(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_GENERAL]);

        $this->actingAs($staff)->get(route('dev.role-switch.edit'))->assertOk();

        $this->actingAs($staff)->put(route('dev.role-switch.update'), [
            'role' => Staff::ROLE_PROCUREMENT_MANAGER,
            'is_supervisor' => '1',
            'is_executive' => '1',
            'is_fund_manager' => '1',
            'is_administrator' => '1',
        ])->assertRedirect(route('dev.role-switch.edit'));

        $staff->refresh();
        $this->assertSame(Staff::ROLE_PROCUREMENT_MANAGER, $staff->role);
        $this->assertTrue($staff->is_supervisor);
        $this->assertTrue($staff->is_executive);
        $this->assertTrue($staff->is_fund_manager);
        $this->assertTrue($staff->is_administrator);
    }

    public function test_unchecked_flags_are_removed(): void
    {
        $staff = Staff::factory()->create([
            'role' => Staff::ROLE_SALES,
            'is_supervisor' => true,
            'is_executive' => true,
            'is_fund_manager' => true,
        ]);

        $this->actingAs($staff)->put(route('dev.role-switch.update'), [
            'role' => Staff::ROLE_GENERAL,
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame(Staff::ROLE_GENERAL, $staff->role);
        $this->assertFalse($staff->is_supervisor);
        $this->assertFalse($staff->is_executive);
        $this->assertFalse($staff->is_fund_manager);
    }

    /**
     * 付与のはしごを通さないため、一般社員でも自分にadministratorを付けられる
     * (これはテスト用途で成立させたい挙動。ＩＤ管理側の制限は別途テストしている)。
     */
    public function test_the_permission_ladder_is_not_applied_here(): void
    {
        $staff = Staff::factory()->create(['role' => Staff::ROLE_GENERAL]);

        $this->actingAs($staff)->put(route('dev.role-switch.update'), [
            'role' => Staff::ROLE_GENERAL,
            'is_administrator' => '1',
        ])->assertRedirect();

        $this->assertTrue($staff->fresh()->is_administrator);
    }

    public function test_the_screen_is_not_available_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $staff = Staff::factory()->create();

        $this->actingAs($staff)
            ->get('/dev/role-switch')
            ->assertNotFound();
    }
}
