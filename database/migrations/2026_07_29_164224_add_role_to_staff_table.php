<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 資材管理担当者(is_procurement_manager)一択だった権限モデルを、資材管理担当者/営業担当/一般社員の
     * 3ロールに拡張する(2026-07-29)。既存のis_procurement_managerの値をroleへ変換してから列を削除する。
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('role')->default('general')->after('is_procurement_manager');
        });

        DB::table('staff')->where('is_procurement_manager', true)->update(['role' => 'procurement_manager']);

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('is_procurement_manager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_procurement_manager')->default(false)->after('email');
        });

        DB::table('staff')->where('role', 'procurement_manager')->update(['is_procurement_manager' => true]);

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
