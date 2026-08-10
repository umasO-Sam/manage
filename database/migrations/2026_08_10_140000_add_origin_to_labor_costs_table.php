<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 人工レコードの作られ方(作業日報のグリッドから / 仕入管理のデータ入力から)を明示する。
 *
 * これまでは daily_report_id の有無で見分けていたが、仕入管理から入れた人工も
 * 同じ日・同じ担当者の日報にぶら下げて確認対象にするため、両方が daily_report_id を
 * 持つようになる。区別が付かないと、本人が日報を出し直したときの作り直し
 * (DailyReportController::syncLaborCosts)が仕入管理から入れた分まで消してしまう。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labor_costs', function (Blueprint $table) {
            $table->string('origin', 20)->default('purchase_input')->index()->after('source');
        });

        // 既存分は従来の見分け方をそのまま引き継ぐ。
        DB::table('labor_costs')->whereNotNull('daily_report_id')->update(['origin' => 'daily_report']);
    }

    public function down(): void
    {
        Schema::table('labor_costs', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
