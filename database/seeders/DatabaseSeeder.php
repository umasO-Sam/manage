<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Staff::factory()->procurementManager()->create([
            'name' => '管理担当者',
            'department' => '資材部',
            'login_id' => 'admin',
            'email' => 'admin@saito-koken.co.jp',
            'password' => bcrypt('password'),
        ]);

        WorkflowType::create([
            'slug' => 'purchase',
            'name' => '購入部品手配',
            'due_date_label' => '希望納期',
            'icon' => 'shopping-cart',
            'accent' => 'blue',
            'allows_reference_order_no' => false,
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '手配中', 'actor_label' => '手配担当者'],
                ['label' => '入荷', 'actor_label' => '受入担当者'],
            ],
            'retention_days' => 7,
        ]);

        WorkflowType::create([
            'slug' => 'estimate',
            'name' => '見積り依頼',
            'due_date_label' => '希望回答期限',
            'icon' => 'file-text',
            'accent' => 'orange',
            'allows_reference_order_no' => true,
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '見積依頼中', 'actor_label' => '手配担当者'],
                ['label' => '回答受領', 'actor_label' => '確認担当者'],
            ],
            'retention_days' => 7,
        ]);
    }
}
