<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プルダウンリストに表示するかどうか。注番が増えると依頼・作業日報・休暇申請の
 * 選択肢が長くなりすぎるため、終わった案件を選択肢から外せるようにする。
 * 既存の注番は今までどおり全件表示のままにする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_numbers', function (Blueprint $table) {
            $table->boolean('show_in_dropdown')->default(true)->after('project_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_numbers', function (Blueprint $table) {
            $table->dropColumn('show_in_dropdown');
        });
    }
};
