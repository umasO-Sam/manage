<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * タイムカード連携の担当者IDは staff.sid をそのまま使う。
 *
 * 本番の実データで突き合わせたところ、SIDを持つ30人のうち29人が timecard 側の wid と一致し、
 * 不一致は0だった(残る1人はタイムカード側に氏名が存在しない)。別の列で二重管理する理由がないため、
 * timecard_wid は廃止して SID を正とする。
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLiteは列を消す前にその列を含むインデックスを落としておく必要がある。
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique('staff_timecard_wid_unique');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('timecard_wid');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedBigInteger('timecard_wid')->nullable()->unique()->after('sid');
        });
    }
};
