<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 「名簿に表示しない」フラグ。テスト用・管理用のアカウントや退職者を、
 * 担当者リスト系の画面から外す。ＩＤ管理そのものからは外さない。
 */
class RosterExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_excluded_staff_are_hidden_from_the_roster_screens(): void
    {
        $manager = Staff::factory()->procurementManager()->create(['name' => '名簿に出る担当者']);
        Staff::factory()->create(['name' => 'テストアカウント', 'excluded_from_rosters' => true]);

        foreach ([
            'daily-reports.list.index',   // 作業日報一覧
            'work-status.index',          // 勤務状況一覧
            'quote-numbers.index',        // 見積番号の採番(社内担当者リスト)
            'projects.create',            // 受注登録(社内担当者リスト)
            'labor-records.index',        // 人工レコード確認(担当者の絞り込み)
        ] as $route) {
            $this->actingAs($manager)->get(route($route))
                ->assertOk()
                ->assertDontSee('テストアカウント')
                ->assertSee('名簿に出る担当者');
        }
    }

    public function test_excluded_staff_are_still_managed_on_the_staff_screen(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Staff::factory()->create(['name' => 'テストアカウント', 'excluded_from_rosters' => true]);

        // ＩＤ管理から消えると設定を戻せなくなるため、こちらには出す
        $this->actingAs($manager)->get(route('staff.index'))
            ->assertOk()
            ->assertSee('テストアカウント');
    }

    public function test_the_flag_can_be_set_from_the_staff_form(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create();

        $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
            'excluded_from_rosters' => '1',
        ])->assertRedirect();

        $this->assertTrue($target->fresh()->excluded_from_rosters);

        $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => $target->role,
            'excluded_from_rosters' => '0',
        ])->assertRedirect();

        $this->assertFalse($target->fresh()->excluded_from_rosters);
    }

    public function test_the_flag_can_be_set_from_the_bulk_edit_table(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = Staff::factory()->create();

        $this->actingAs($manager)->post(route('staff.bulk-update'), [
            'updates' => [
                $target->id => [
                    'name' => $target->name, 'department' => $target->department,
                    'login_id' => $target->login_id, 'email' => $target->email, 'role' => $target->role,
                    'excluded_from_rosters' => '1',
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('staff.index'));

        $this->assertTrue($target->fresh()->excluded_from_rosters);
    }
}
