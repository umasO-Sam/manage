<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 上部メニューの権限表(資材管理担当者/上長/営業担当/一般社員)どおりに
 * メニュー項目が出し分けられ、かつ表示された項目に実際にアクセスできることを確認する。
 */
class NavigationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 一般社員のナビゲーションは「購入手配」「見積依頼」ボードを直接リンクとして出すため、
        // 対応するWorkflowTypeが無いとリンク自体が描画されない。
        foreach ([['purchase', '購入手配', 'shopping-cart'], ['estimate', '見積依頼', 'file-text']] as [$slug, $name, $icon]) {
            WorkflowType::create([
                'slug' => $slug,
                'name' => $name,
                'due_date_label' => '希望納期',
                'icon' => $icon,
                'accent' => 'blue',
                'stage_definition' => [['label' => '新規依頼', 'actor_label' => '依頼者']],
                'retention_days' => 7,
            ]);
        }
    }

    private function navHtmlFor(Staff $staff): string
    {
        return $this->actingAs($staff)->get(route('my-calendar.show'))->getContent();
    }

    public function test_procurement_manager_sees_all_menu_groups(): void
    {
        $html = $this->navHtmlFor(Staff::factory()->procurementManager()->create());

        foreach (['調達ボード', '勤怠管理', '仕入管理', 'システム管理', '人工レコード', '休日マスタ', 'ＩＤ管理', 'データ入力'] as $label) {
            $this->assertStringContainsString($label, $html, "資材管理担当者に「{$label}」が表示されていません。");
        }
    }

    public function test_supervisor_sees_attendance_and_purchasing_but_not_manager_only_items(): void
    {
        $html = $this->navHtmlFor(Staff::factory()->create(['is_supervisor' => true]));

        foreach (['調達ボード', '勤怠管理', '仕入管理', '作業日報一覧', '申請承認', '操作ログ', '原価一覧'] as $label) {
            $this->assertStringContainsString($label, $html, "上長に「{$label}」が表示されていません。");
        }
        // 作業日報確認は人工データの確定を伴う資材管理担当者の業務のため、上長には出さない。
        foreach (['作業日報確認', '人工レコード', '休日マスタ', 'システム管理', 'データ入力', '注文書発行'] as $label) {
            $this->assertStringNotContainsString($label, $html, "上長に「{$label}」が表示されています。");
        }
    }

    public function test_sales_sees_purchasing_but_not_supervisor_items(): void
    {
        $html = $this->navHtmlFor(Staff::factory()->create(['role' => Staff::ROLE_SALES]));

        foreach (['調達ボード', '勤怠管理', '仕入管理', '見積補助', '原価計算', '人工計算'] as $label) {
            $this->assertStringContainsString($label, $html, "営業担当に「{$label}」が表示されていません。");
        }
        foreach (['作業日報確認', '作業日報一覧', '申請承認', '操作ログ', '原価一覧', 'システム管理'] as $label) {
            $this->assertStringNotContainsString($label, $html, "営業担当に「{$label}」が表示されています。");
        }
    }

    public function test_general_staff_sees_boards_directly_and_only_shared_attendance_items(): void
    {
        $html = $this->navHtmlFor(Staff::factory()->create());

        foreach (['購入手配ボード', '見積依頼ボード', '履歴', '勤怠管理', '個人カレンダー', '作業日報', '休暇・休出申請', '勤務状況一覧'] as $label) {
            $this->assertStringContainsString($label, $html, "一般社員に「{$label}」が表示されていません。");
        }
        foreach (['作業日報確認', '作業日報一覧', '申請承認', '操作ログ', '人工レコード', '仕入管理', 'システム管理'] as $label) {
            $this->assertStringNotContainsString($label, $html, "一般社員に「{$label}」が表示されています。");
        }
    }

    public function test_supervisor_can_access_the_screens_shown_in_their_menu(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);

        foreach ([
            'daily-reports.list.index',
            'operation-logs.index',
            'leave-requests.approvals',
            'purchasing.index',
            'purchasing.labor.index',
            'purchasing.cost-report.index',
        ] as $routeName) {
            $this->actingAs($supervisor)->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_supervisor_cannot_access_the_daily_report_review_screen(): void
    {
        $supervisor = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($supervisor)->get(route('daily-reports.review.index'))->assertForbidden();
    }

    public function test_sales_cannot_access_supervisor_only_screens(): void
    {
        $sales = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        foreach (['daily-reports.review.index', 'daily-reports.list.index', 'operation-logs.index', 'purchasing.cost-report.index'] as $routeName) {
            $this->actingAs($sales)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_general_staff_cannot_access_purchasing_screens(): void
    {
        $staff = Staff::factory()->create();

        foreach (['purchasing.index', 'purchasing.labor.index', 'purchasing.cost.index'] as $routeName) {
            $this->actingAs($staff)->get(route($routeName))->assertForbidden();
        }
    }
}
