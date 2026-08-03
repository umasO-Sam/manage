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
        Schema::create('daily_report_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            // 0:00からの経過分(0-1439)で開始・終了を保持する。TIME型の日付境界文字列比較を避け、
            // 範囲重複判定・分数集計を単純な整数算術で行うため。
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->string('order_no')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('category_codes')->restrictOnDelete();
            $table->boolean('is_other')->default(false);
            $table->string('free_text')->nullable();
            $table->boolean('is_break')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_entries');
    }
};
