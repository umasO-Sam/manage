<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 作業日報の「選択中」に出る分類の説明(category_codes.item_name)の編集。
 * 全員に見える共通の表記のため、日報管理者・勤怠管理者・役員・資金管理者・
 * administratorだけが直せる。
 */
class CategoryItemNameEditTest extends TestCase
{
    use RefreshDatabase;

    private function category(): CategoryCode
    {
        return CategoryCode::create([
            'code' => 59,
            'major_category' => '社内人工',
            'sub_category' => '機械製缶',
            'item_name' => '切出・製缶・塗装・部品製作',
        ]);
    }

    /** @return array<string, Staff> */
    private function allowedStaff(): array
    {
        return [
            '日報管理者' => Staff::factory()->create(['is_daily_report_reviewer' => true]),
            '勤怠管理者' => Staff::factory()->create(['is_attendance_manager' => true]),
            '役員' => Staff::factory()->create(['is_executive' => true]),
            '資金管理者' => Staff::factory()->create(['is_fund_manager' => true]),
            'administrator' => Staff::factory()->create(['is_administrator' => true]),
        ];
    }

    public function test_permitted_staff_can_rewrite_the_description(): void
    {
        $category = $this->category();

        foreach ($this->allowedStaff() as $label => $staff) {
            $this->actingAs($staff)
                ->putJson(route('category-codes.item-name.update', $category), ['item_name' => "{$label}が直した内訳"])
                ->assertOk()
                ->assertJson(['item_name' => "{$label}が直した内訳", 'changed' => true]);

            $this->assertSame("{$label}が直した内訳", $category->fresh()->item_name);
        }
    }

    public function test_other_staff_cannot_rewrite_the_description(): void
    {
        $category = $this->category();

        foreach ([
            '一般社員' => Staff::factory()->create(),
            '営業担当' => Staff::factory()->sales()->create(),
            '経理資材担当' => Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => false]),
            '上長' => Staff::factory()->create(['is_supervisor' => true]),
        ] as $label => $staff) {
            $this->actingAs($staff)
                ->putJson(route('category-codes.item-name.update', $category), ['item_name' => '書き換え'])
                ->assertForbidden("{$label}が分類の説明を書き換えられます。");
        }

        $this->assertSame('切出・製缶・塗装・部品製作', $category->fresh()->item_name);
    }

    /** 誰が何をどう変えたかを操作ログに残す(全員の画面に出る表記のため)。 */
    public function test_the_change_is_written_to_the_operation_log(): void
    {
        $category = $this->category();
        $staff = Staff::factory()->create(['is_daily_report_reviewer' => true]);

        $this->actingAs($staff)
            ->putJson(route('category-codes.item-name.update', $category), ['item_name' => '切出・製缶・塗装'])
            ->assertOk();

        $log = OperationLog::where('subject_type', CategoryCode::class)->where('subject_id', $category->id)->sole();

        $this->assertSame(OperationLog::ACTION_CATEGORY_ITEM_NAME_UPDATE, $log->action);
        $this->assertSame($staff->id, $log->staff_id);
        $this->assertStringContainsString('切出・製缶・塗装・部品製作', $log->description);
        $this->assertStringContainsString('切出・製缶・塗装', $log->description);
    }

    /** 空にすると未設定に戻す。中身が変わらないときはログを増やさない。 */
    public function test_an_empty_value_clears_it_and_an_unchanged_value_is_not_logged(): void
    {
        $category = $this->category();
        $staff = Staff::factory()->create(['is_administrator' => true]);

        $this->actingAs($staff)
            ->putJson(route('category-codes.item-name.update', $category), ['item_name' => '切出・製缶・塗装・部品製作'])
            ->assertOk()
            ->assertJson(['changed' => false]);
        $this->assertSame(0, OperationLog::where('subject_type', CategoryCode::class)->count());

        $this->actingAs($staff)
            ->putJson(route('category-codes.item-name.update', $category), ['item_name' => '   '])
            ->assertOk();
        $this->assertNull($category->fresh()->item_name);
    }

    /** 編集ボタンは対象者にだけ出す。 */
    public function test_the_edit_button_is_shown_only_to_permitted_staff(): void
    {
        $this->category();

        foreach ($this->allowedStaff() as $label => $staff) {
            $this->actingAs($staff)->get(route('daily-reports.show'))
                ->assertOk()->assertSee('説明を編集', false, "{$label}に編集ボタンが出ません。");
        }

        $this->actingAs(Staff::factory()->create())->get(route('daily-reports.show'))
            ->assertOk()->assertDontSee('説明を編集', false);
    }
}
