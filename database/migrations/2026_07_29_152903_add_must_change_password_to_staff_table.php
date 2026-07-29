<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * デフォルトtrueにすることで、既存の全アカウント(ALTER TABLE時にバックフィルされる)・
     * 今後新規作成されるアカウントの両方に、次回ログイン時のパスワード変更を要求する
     * (仕入管理データの機密性向上のためのセキュリティ強化、2026-07-29)。
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(true)->after('is_procurement_manager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
