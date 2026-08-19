<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardComment;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 参照ユーザ(role=viewer)。購入手配ボードの参照と勤務状況一覧の閲覧だけができ、
 * 新規依頼の作成・コメント投稿・他の画面はすべて403になる。
 */
class ReferenceViewerTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseWorkflow(): WorkflowType
    {
        return WorkflowType::create([
            'slug' => 'purchase',
            'name' => '購入手配',
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

    private function estimateWorkflow(): WorkflowType
    {
        return WorkflowType::create([
            'slug' => 'estimate',
            'name' => '見積依頼',
            'due_date_label' => '希望回答期限',
            'icon' => 'file-text',
            'accent' => 'orange',
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '見積依頼中', 'actor_label' => '手配担当者'],
                ['label' => '回答受領', 'actor_label' => '確認担当者'],
            ],
            'retention_days' => 7,
        ]);
    }

    private int $orderNumberSequence = 0;

    private function makeCard(WorkflowType $workflow, Staff $creator): Card
    {
        $orderNumber = OrderNumber::create([
            'code' => sprintf('ZZ%03d-N99T99', ++$this->orderNumberSequence),
            'is_protected' => false,
        ]);

        return $workflow->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品',
            'quantity' => 1, 'unit' => '個', 'due_date_type' => 'asap',
            'created_by' => $creator->id, 'current_stage' => 0,
        ]);
    }

    public function test_viewer_can_open_the_purchase_board(): void
    {
        $workflow = $this->purchaseWorkflow();
        $viewer = Staff::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('cards.index', $workflow))->assertOk();
    }

    public function test_viewer_cannot_open_the_estimate_board(): void
    {
        $this->purchaseWorkflow();
        $estimate = $this->estimateWorkflow();
        $viewer = Staff::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('cards.index', $estimate))->assertForbidden();
    }

    public function test_viewer_is_not_offered_the_create_button(): void
    {
        $workflow = $this->purchaseWorkflow();
        $viewer = Staff::factory()->viewer()->create();
        $general = Staff::factory()->create();

        $this->actingAs($viewer)->get(route('cards.index', $workflow))
            ->assertOk()->assertDontSeeText('新規依頼を作成');

        // 一般社員には今までどおり出る。
        $this->actingAs($general)->get(route('cards.index', $workflow))
            ->assertOk()->assertSeeText('新規依頼を作成');
    }

    public function test_viewer_cannot_create_a_card(): void
    {
        $workflow = $this->purchaseWorkflow();
        $viewer = Staff::factory()->viewer()->create();
        $orderNumber = OrderNumber::create(['code' => 'ZZ900-N99T99', 'is_protected' => false]);

        $this->actingAs($viewer)->get(route('cards.create', $workflow))->assertForbidden();

        $this->actingAs($viewer)->post(route('cards.store', $workflow), [
            'order_number_id' => $orderNumber->id,
            'item_name' => '勝手に作った部品',
            'quantity' => 1,
            'unit' => '個',
            'due_date_type' => 'asap',
        ])->assertForbidden();

        $this->assertSame(0, Card::count());
    }

    public function test_viewer_can_open_a_purchase_card_but_not_an_estimate_card(): void
    {
        $purchase = $this->purchaseWorkflow();
        $estimate = $this->estimateWorkflow();
        $viewer = Staff::factory()->viewer()->create();
        $creator = Staff::factory()->create();

        $this->actingAs($viewer)->get(route('cards.show', $this->makeCard($purchase, $creator)))->assertOk();
        $this->actingAs($viewer)->get(route('cards.show', $this->makeCard($estimate, $creator)))->assertForbidden();
    }

    public function test_viewer_cannot_comment_and_is_not_shown_the_form(): void
    {
        $purchase = $this->purchaseWorkflow();
        $viewer = Staff::factory()->viewer()->create();
        $card = $this->makeCard($purchase, Staff::factory()->create());

        $this->actingAs($viewer)->get(route('cards.show', $card))
            ->assertOk()->assertDontSeeText('コメントする');

        $this->actingAs($viewer)->post(route('cards.comments.store', $card), ['body' => '書けるはずがない'])
            ->assertForbidden();

        $this->assertSame(0, CardComment::count());
    }

    public function test_viewer_can_open_the_work_status_list(): void
    {
        $this->purchaseWorkflow();
        $viewer = Staff::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('work-status.index'))->assertOk();
    }

    public function test_viewer_can_still_manage_their_own_account(): void
    {
        $this->purchaseWorkflow();
        $viewer = Staff::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('profile.edit'))->assertOk();
    }

    /**
     * 許可した画面以外はすべて403。画面が増えたときの付け忘れを防ぐため、
     * ミドルウェアで許可制にしている。
     */
    public function test_every_other_screen_is_forbidden_for_a_viewer(): void
    {
        $this->purchaseWorkflow();
        $viewer = Staff::factory()->viewer()->create();

        $forbidden = [
            route('archive.index'),
            route('daily-reports.show'),
            route('my-calendar.show'),
            route('leave-requests.index'),
            route('leave-requests.create'),
            route('purchasing.index'),
            route('staff.index'),
            route('order-numbers.index'),
            route('operation-logs.index'),
            route('projects.index'),
        ];

        foreach ($forbidden as $url) {
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }
    }

    public function test_viewer_menu_shows_only_the_board_and_the_work_status_list(): void
    {
        $workflow = $this->purchaseWorkflow();
        $this->estimateWorkflow();
        $viewer = Staff::factory()->viewer()->create();

        $response = $this->actingAs($viewer)->get(route('cards.index', $workflow))->assertOk();

        $response->assertSeeText('購入手配ボード');
        $response->assertSeeText('勤務状況一覧');
        foreach (['見積依頼ボード', '履歴', '作業日報', '休暇・休出申請', '仕入管理', 'ＩＤ管理'] as $hidden) {
            $response->assertDontSeeText($hidden);
        }
    }

    /**
     * 参照ユーザに上長・役員などのフラグが付くと、ロールとフラグで権限判定が
     * 食い違ってしまう。保存時にまとめて落とす。
     */
    public function test_privilege_flags_are_dropped_when_an_account_becomes_a_viewer(): void
    {
        $manager = Staff::factory()->procurementManager()->create(['is_administrator' => true]);
        $target = Staff::factory()->create(['is_supervisor' => true, 'is_daily_report_reviewer' => true]);

        $this->actingAs($manager)->put(route('staff.update', $target), [
            'name' => $target->name,
            'department' => $target->department,
            'login_id' => $target->login_id,
            'email' => $target->email,
            'role' => Staff::ROLE_VIEWER,
            'is_supervisor' => '1',
            'is_daily_report_reviewer' => '1',
            'excluded_from_rosters' => '1',
        ])->assertRedirect(route('staff.index'));

        $fresh = $target->fresh();
        $this->assertSame(Staff::ROLE_VIEWER, $fresh->role);
        $this->assertFalse((bool) $fresh->is_supervisor);
        $this->assertFalse((bool) $fresh->is_daily_report_reviewer);
        // 名簿からの除外は権限ではないので、指定どおり残る。
        $this->assertTrue((bool) $fresh->excluded_from_rosters);
    }
}
