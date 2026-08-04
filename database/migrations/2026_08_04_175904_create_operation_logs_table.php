<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            // 実行者(操作を行った担当者)。監査ログのため、担当者削除時も記録を消さずに残す。
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            // 対象の本人(作業日報の提出者・休暇/勤務申請の申請者)。一般社員・営業担当が
            // 「自分の申請・承認ログ」を絞り込む際は、実行者ではなくこちらで判定する
            // (上長が本人の日報を確認・差し戻した場合も本人から見えるようにするため)。
            $table->foreignId('owner_staff_id')->constrained('staff')->restrictOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['owner_staff_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
