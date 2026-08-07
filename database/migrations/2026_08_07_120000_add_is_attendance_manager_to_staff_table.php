<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 勤怠管理フラグ。承認済みの休暇・休出申請を取り消す際、上長が認めたあとに
 * 「法律やルールに照らして取り消してよいか、別の申請を出し直してもらうべきか」
 * を判断する担当者を表す。日報管理者(is_daily_report_reviewer)と同じく、
 * ロールに重ねて付与するフラグにしている。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_attendance_manager')->default(false)->after('is_daily_report_reviewer');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('is_attendance_manager');
        });
    }
};
