<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 勤怠管理者が本人に代わって作業日報を提出できるようにする。
 * 誰が代理で出したかを持たないと、確認で差し戻したときに本人へ返してしまい、
 * 代理入力した勤怠管理者が気づけない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->foreignId('proxy_staff_id')->nullable()->after('staff_id')
                ->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proxy_staff_id');
        });
    }
};
