<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * retention_days を null 許容にする。nullは「自動アーカイブしない」を意味し、
 * 物件管理ボードのように入金済に到達しても人が非表示にするまで残すボードで使う。
 *
 * 0を入れて代用することはできない。ArchiveCompletedCardsは
 * now()->subDays($retention_days) を締め切りにしているため、0だと
 * 最終ステージへ到達した瞬間にすべてアーカイブされてしまう。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('retention_days')->default(7)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('retention_days')->default(7)->nullable(false)->change();
        });
    }
};
