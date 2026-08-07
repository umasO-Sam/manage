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

    /**
     * パスワードを再設定できる＝そのアカウントでログインできる、ということなので、
     * 自分より上の権限のアカウントは氏名すら編集できない（権限昇格の抜け道を塞ぐ）。
     */
    public function test_accounts_above_the_actor_cannot_be_edited(): void
    {
        $manager = $this->manager();

        foreach ([$this->executive(), $this->fundManager(), $this->administrator()] as $target) {
            $this->actingAs($manager)
                ->put(route('staff.update', $target), $this->payload($target, ['name' => '書き換え']))
                ->assertSessionHasErrors('role');

            $this->assertNotSame('書き換え', $target->fresh()->name);
        }

        // 編集フォーム自体も開けない。
        $this->actingAs($manager)->get(route('staff.edit', $this->executive()))->assertForbidden();
    }

    public function test_same_level_accounts_can_edit_each_other(): void
    {
        $executiveA = $this->executive();
        $executiveB = $this->executive();

        $this->actingAs($executiveA)
            ->put(route('staff.update', $executiveB), $this->payload($executiveB, ['name' => '役員B改']))
            ->assertRedirect();

        $this->assertSame('役員B改', $executiveB->fresh()->name);
    }

    public function test_a_fund_manager_can_edit_an_executive(): void
    {
        $executive = $this->executive();

        $this->actingAs($this->fundManager())
            ->put(route('staff.update', $executive), $this->payload($executive, ['name' => '役員改', 'paid_leave_granted_current_year' => 12]))
            ->assertRedirect();

        $executive->refresh();
        $this->assertSame('役員改', $executive->name);
        $this->assertSame(12.0, (float) $executive->paid_leave_granted_current_year);
    }

    /**
     * 直接編集の表には上長のチェックボックスしか無い。チェックボックスは未チェックだと
     * キーごと送信されないため、これを「オフにする指示」と解釈すると、
     * 役員・資金管理者・administratorが剥がれる(最後の1人ガードに当たって保存自体も失敗する)。
     * 画面に無い項目は現在値を据え置くこと。
     */
    public function test_the_bulk_edit_table_does_not_strip_flags_it_does_not_show(): void
    {
        $admin = $this->administrator();
        $fund = $this->fundManager();
        $executive = $this->executive();
        // 経理資材担当が0人になる保存は別のガードで弾かれるため、1人残しておく
        $this->manager();

        $updates = [];
        foreach ([$admin, $fund, $executive] as $target) {
            $updates[$target->id] = [
                'name' => $target->name, 'department' => $target->department, 'login_id' => $target->login_id,
                'email' => $target->email, 'role' => $target->role,
                // 表に出ている上長だけが送られてくる
                'is_supervisor' => '0',
            ];
        }

        $this->actingAs($admin)->post(route('staff.bulk-update'), ['updates' => $updates])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.index'));

        $this->assertTrue($admin->fresh()->is_administrator);
        $this->assertTrue($fund->fresh()->is_fund_manager);
        $this->assertTrue($executive->fresh()->is_executive);
    }

    /**
     * 画面に出ているチェックボックスは、外したらきちんと外れること
     * (hiddenの0を添えているので「変更の指示なし」とは区別される)。
     */
    public function test_a_checkbox_on_the_form_can_still_be_turned_off(): void
    {
        $admin = $this->administrator();
        $target = Staff::factory()->create(['is_supervisor' => true, 'is_executive' => true]);

        $this->actingAs($admin)->put(route('staff.update', $target), $this->payload($target, [
            'is_supervisor' => '0',
            'is_executive' => '0',
        ]))->assertRedirect();

        $target->refresh();
        $this->assertFalse($target->is_supervisor);
        $this->assertFalse($target->is_executive);
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
        $executive = $this->executive();
        // 役員は役員を編集できるが、資金管理者フラグは触れない。
        $target = Staff::factory()->create(['is_executive' => true]);

        $this->actingAs($executive)
            ->put(route('staff.update', $target), $this->payload($target, ['name' => '氏名変更', 'is_executive' => '1']))
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('氏名変更', $target->name);
        $this->assertTrue($target->is_executive);
        $this->assertFalse($target->is_fund_manager);
    }

    public function test_administrators_are_treated_as_procurement_managers(): void
    {
        // administratorはすべての機能を使うため、資材管理担当者限定の画面にも入れる。
        $this->actingAs($this->administrator())->get(route('purchasing.input'))->assertOk();
    }

    /**
     * 勤怠管理フラグははしごとは別の判定で、役員・勤怠管理者・administratorだけが
     * 付け外しできる。上長や経理資材担当は担当者管理を開けても操作できない。
     */
    public function test_only_executives_attendance_managers_and_administrators_can_grant_the_attendance_flag(): void
    {
        // 勤怠管理フラグだけでは担当者管理を開けないため、画面に入れる立場と併せ持つ場合を見る。
        $attendanceManager = Staff::factory()->procurementManager()->create(['is_attendance_manager' => true]);

        foreach ([$this->administrator(), $this->executive(), $attendanceManager] as $actor) {
            $target = Staff::factory()->create();

            $this->actingAs($actor)
                ->put(route('staff.update', $target), $this->payload($target, ['is_attendance_manager' => '1']))
                ->assertRedirect();

            $this->assertTrue($target->fresh()->is_attendance_manager);
        }
    }

    public function test_procurement_managers_and_supervisors_cannot_grant_the_attendance_flag(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true, 'role' => Staff::ROLE_PROCUREMENT_MANAGER]);

        foreach ([$this->manager(), $supervisor] as $actor) {
            $target = Staff::factory()->create();

            $this->actingAs($actor)
                ->put(route('staff.update', $target), $this->payload($target, ['is_attendance_manager' => '1']))
                ->assertRedirect();

            $this->assertFalse($target->fresh()->is_attendance_manager, '付与を許されていない実行者の指示は無視する');
        }
    }

    public function test_an_existing_attendance_flag_is_kept_when_the_actor_cannot_grant_it(): void
    {
        $target = Staff::factory()->create(['is_attendance_manager' => true]);

        // 経理資材担当が他項目を編集しても、勤怠管理フラグは据え置かれる。
        $this->actingAs($this->manager())
            ->put(route('staff.update', $target), $this->payload($target, ['name' => '氏名変更']))
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('氏名変更', $target->name);
        $this->assertTrue($target->is_attendance_manager, '勝手に外れないこと');
    }

    public function test_the_attendance_flag_is_protected_on_the_bulk_edit_screen_too(): void
    {
        $target = Staff::factory()->create();

        $this->actingAs($this->manager())
            ->post(route('staff.bulk-update'), ['updates' => [
                $target->id => [
                    'name' => $target->name,
                    'department' => $target->department,
                    'login_id' => $target->login_id,
                    'email' => $target->email,
                    'role' => $target->role,
                    'is_attendance_manager' => '1',
                ],
            ]])->assertRedirect();

        $this->assertFalse($target->fresh()->is_attendance_manager);
    }

    /**
     * 勤怠管理フラグ単独では担当者管理を開けない。パスワードを再設定できる画面なので、
     * 開ける範囲は従来どおり経理資材担当・役員・資金管理者のまま据え置いている。
     */
    public function test_the_attendance_flag_alone_does_not_open_the_staff_screen(): void
    {
        $this->actingAs(Staff::factory()->create(['is_attendance_manager' => true]))
            ->get(route('staff.index'))->assertForbidden();
    }

    public function test_the_staff_list_shows_the_attendance_column(): void
    {
        Staff::factory()->create(['is_attendance_manager' => true, 'name' => '勤怠担当さん']);

        $this->actingAs($this->administrator())->get(route('staff.index'))
            ->assertOk()
            ->assertSee('勤怠管理')
            ->assertSee('勤怠担当さん');
    }
}
