<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 権限付与のはしご: 経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator。
 * 自分より上のフラグは付け外しできない。画面上で操作できないようにするだけでなく、
 * 直接編集や改ざんされたリクエストでも必ずサーバー側で落とす。
 */
class PermissionLadderTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): Staff
    {
        return Staff::factory()->procurementManager()->create();
    }

    private function executive(): Staff
    {
        return Staff::factory()->create(['is_executive' => true]);
    }

    private function fundManager(): Staff
    {
        return Staff::factory()->create(['is_fund_manager' => true]);
    }

    private function administrator(): Staff
    {
        return Staff::factory()->create(['is_administrator' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(Staff $target, array $overrides = []): array
    {
        return [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
            ...$overrides,
        ];
    }

    public function test_executives_and_fund_managers_can_open_the_staff_screen(): void
    {
        $this->actingAs($this->executive())->get(route('staff.index'))->assertOk();
        $this->actingAs($this->fundManager())->get(route('staff.index'))->assertOk();
        $this->actingAs($this->manager())->get(route('staff.index'))->assertOk();
    }

    public function test_general_staff_cannot_open_the_staff_screen(): void
    {
        $this->actingAs(Staff::factory()->create())->get(route('staff.index'))->assertForbidden();
    }

    public function test_procurement_manager_cannot_grant_the_executive_flag(): void
    {
        $target = Staff::factory()->create();

        $this->actingAs($this->manager())
            ->put(route('staff.update', $target), $this->payload($target, ['is_executive' => '1']))
            ->assertRedirect();

        $this->assertFalse($target->fresh()->is_executive);
    }

    public function test_executive_can_grant_the_executive_flag_but_not_fund_manager(): void
    {
        $target = Staff::factory()->create();

        $this->actingAs($this->executive())
            ->put(route('staff.update', $target), $this->payload($target, ['is_executive' => '1', 'is_fund_manager' => '1']))
            ->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->is_executive);
        $this->assertFalse($target->is_fund_manager);
    }

    public function test_fund_manager_can_grant_fund_manager_but_not_administrator(): void
    {
        $target = Staff::factory()->create();

        $this->actingAs($this->fundManager())
            ->put(route('staff.update', $target), $this->payload($target, ['is_fund_manager' => '1', 'is_administrator' => '1']))
            ->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->is_fund_manager);
        $this->assertFalse($target->is_administrator);
    }

    public function test_only_administrators_can_grant_administrator(): void
    {
        $target = Staff::factory()->create();

        $this->actingAs($this->administrator())
            ->put(route('staff.update', $target), $this->payload($target, ['is_administrator' => '1']))
            ->assertRedirect();

        $this->assertTrue($target->fresh()->is_administrator);
    }

    public function test_the_bulk_edit_table_cannot_be_used_to_bypass_the_ladder(): void
    {
        $manager = $this->manager();
        $target = Staff::factory()->create();

        $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [
                $target->id => [
                    'name' => $target->name,
                    'department' => $target->department,
                    'login_id' => $target->login_id,
                    'email' => $target->email,
                    'role' => $target->role,
                    'is_executive' => '1',
                    'is_fund_manager' => '1',
                    'is_administrator' => '1',
                ],
            ],
        ])->assertRedirect();

        $target->refresh();
        $this->assertFalse($target->is_executive);
        $this->assertFalse($target->is_fund_manager);
        $this->assertFalse($target->is_administrator);
    }

    public function test_administrator_accounts_cannot_be_edited_by_others(): void
    {
        $admin = $this->administrator();

        $this->actingAs($this->fundManager())
            ->put(route('staff.update', $admin), $this->payload($admin, ['name' => '書き換え']))
            ->assertSessionHasErrors('role');

        $this->assertNotSame('書き換え', $admin->fresh()->name);
    }

    public function test_administrator_accounts_cannot_be_deleted_by_others(): void
    {
        $admin = $this->administrator();

        $this->actingAs($this->fundManager())
            ->delete(route('staff.destroy', $admin))
            ->assertSessionHasErrors('delete');

        $this->assertNotNull($admin->fresh());
    }

    public function test_the_last_fund_manager_cannot_be_demoted(): void
    {
        $admin = $this->administrator();
        $onlyFundManager = $this->fundManager();

        $this->actingAs($admin)
            ->put(route('staff.update', $onlyFundManager), $this->payload($onlyFundManager, ['is_fund_manager' => '0']))
            ->assertSessionHasErrors('role');

        $this->assertTrue($onlyFundManager->fresh()->is_fund_manager);
    }

    public function test_the_last_fund_manager_cannot_be_deleted(): void
    {
        $admin = $this->administrator();
        $onlyFundManager = $this->fundManager();

        $this->actingAs($admin)->delete(route('staff.destroy', $onlyFundManager))->assertSessionHasErrors('delete');

        $this->assertNotNull($onlyFundManager->fresh());
    }

    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        $admin = $this->administrator();
        $other = Staff::factory()->create();

        // 自分自身のadministratorを外そうとする(他にadministratorがいない)
        $this->actingAs($admin)
            ->put(route('staff.update', $admin), $this->payload($admin, ['is_administrator' => '0']))
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->is_administrator);

        // 別のadministratorがいれば外せる
        $other->update(['is_administrator' => true]);
        $this->actingAs($admin)
            ->put(route('staff.update', $admin), $this->payload($admin, ['is_administrator' => '0']))
            ->assertRedirect();

        $this->assertFalse($admin->fresh()->is_administrator);
    }

    public function test_a_flag_the_actor_cannot_grant_is_left_untouched_rather_than_cleared(): void
    {
        $manager = $this->manager();
        $target = Staff::factory()->create(['is_executive' => true, 'is_fund_manager' => true]);

        // 経理資材担当が氏名だけ直す。役員・資金管理者のチェックは画面に出ないので送信されない。
        $this->actingAs($manager)
            ->put(route('staff.update', $target), $this->payload($target, ['name' => '氏名変更']))
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('氏名変更', $target->name);
        $this->assertTrue($target->is_executive);
        $this->assertTrue($target->is_fund_manager);
    }

    public function test_administrators_are_treated_as_procurement_managers(): void
    {
        // administratorはすべての機能を使うため、資材管理担当者限定の画面にも入れる。
        $this->actingAs($this->administrator())->get(route('purchasing.input'))->assertOk();
    }
}
