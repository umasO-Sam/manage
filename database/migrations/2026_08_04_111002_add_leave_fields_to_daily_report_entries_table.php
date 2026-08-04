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
        Schema::table('daily_report_entries', function (Blueprint $table) {
            // 休暇(有給休暇取得時間帯のメモ)。人工・原価集計の対象外とするため、
            // 分類コード(category_id)とは別枠のフラグとして持つ。
            $table->boolean('is_leave')->default(false);
            $table->string('leave_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_report_entries', function (Blueprint $table) {
            $table->dropColumn(['is_leave', 'leave_type']);
        });
    }
};
