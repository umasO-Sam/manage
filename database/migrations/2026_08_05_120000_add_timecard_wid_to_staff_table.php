<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 別システム「timecard-new」の担当者ID(card.wid / stuff.wid)との紐づけ。
 * 氏名は表記ゆれ(全角スペースの有無など)で完全一致しないため、突き合わせは
 * 氏名ではなくこの列で行う(app:map-timecard-staff で正規化した氏名から初期設定できる)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedBigInteger('timecard_wid')->nullable()->unique()->after('sid');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('timecard_wid');
        });
    }
};
