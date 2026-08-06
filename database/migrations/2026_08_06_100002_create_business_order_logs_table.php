<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 物件履歴。物件管理ボードの操作をすべて残す専用テーブルで、期間による削除は行わない。
 *
 * 既存のカード履歴(CardStageLog/CardEditLog)はカードを完全削除すると一緒に消え、
 * operation_logsは5年で削除される。物件は「非表示後も削除しない」方針のため、
 * どちらにも寄せず独立したテーブルにしている。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('action', 40);          // created / stage_moved / stage_reverted / attachment_added / trade_terms_confirmed / hidden
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_order_logs');
    }
};
