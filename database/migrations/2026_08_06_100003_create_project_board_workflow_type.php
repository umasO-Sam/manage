<?php

use App\Models\WorkflowType;
use Illuminate\Database\Migrations\Migration;

/**
 * 物件管理ボードのワークフロー定義。
 *
 * 各ステージへ進む条件は stage_definition の requires に持たせる。
 *   attachment          … 指定の種別の添付が必要
 *   sales_date          … 受注ヘッダの売上日が必要
 *   attachment_or_flag  … 添付、または請求済チェックのどちらか
 *   blocked_when_pending… 取引条件調整中は進めない(2026-08-20に撤廃。後続のマイグレーションで外している)
 *
 * retention_days は null。自動アーカイブ・5年削除の対象外で、入金済のあと
 * 資金管理者が非表示ボタンを押したときだけアーカイブする。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (WorkflowType::where('slug', 'project')->exists()) {
            return;
        }

        WorkflowType::create([
            'slug' => 'project',
            'name' => '物件管理',
            'due_date_label' => '受注日',
            'icon' => 'building-2',
            'accent' => 'blue',
            'stage_definition' => [
                ['label' => '受注', 'actor_label' => '受注登録者'],
                ['label' => '線表反映済', 'actor_label' => '反映担当者'],
                [
                    'label' => '部品発送・検収済',
                    'actor_label' => '確認担当者',
                    'requires' => ['attachment' => 'completion_proof', 'sales_date' => true],
                ],
                [
                    'label' => '納品書送付済',
                    'actor_label' => '送付担当者',
                    'requires' => ['attachment' => 'delivery_note'],
                ],
                [
                    'label' => '請求済',
                    'actor_label' => '請求担当者',
                    'requires' => ['attachment_or_flag' => 'invoice', 'blocked_when_pending' => true],
                ],
                ['label' => '入金済', 'actor_label' => '確認担当者'],
            ],
            'retention_days' => null,
        ]);
    }

    public function down(): void
    {
        WorkflowType::where('slug', 'project')->delete();
    }
};
