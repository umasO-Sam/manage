<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 日報管理者フラグ。作業日報の確認は経理資材担当のうち特定のメンバーが行うため、
 * 画面と未確認バッジをそのメンバーだけに絞る。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_daily_report_reviewer')->default(false)->after('excluded_from_rosters');
        });

        // 既存の経理資材担当は全員が確認できていたため、まず現状のまま引き継ぐ。
        // 担当しないメンバーはＩＤ管理でチェックを外して絞り込む。
        DB::table('staff')->where('role', 'procurement_manager')->update(['is_daily_report_reviewer' => true]);
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('is_daily_report_reviewer');
        });
    }
};
