<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 役員・資金管理者・administratorを、上長(is_supervisor)と同じ「ロールに重ねて付与するフラグ」として追加する。
 *
 * roleを4値目に増やす案もあったが、開発DBの役員部署7人のうち4人が営業担当(sales)であり、
 * roleにしてしまうとその4人が仕入管理の検索・原価計算・人工計算を見られなくなるため、
 * 「役員かつ営業担当」を表現できるフラグにした。administratorも同じ理由でフラグにしている
 * (roleを置き換えると本番の管理アカウントがprocurement_managerでなくなり、
 * is_procurement_managerを見ている既存の権限判定が一斉に効かなくなる)。
 *
 * 編集できる範囲は 経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator の入れ子。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_executive')->default(false)->after('is_supervisor');
            $table->boolean('is_fund_manager')->default(false)->after('is_executive');
            $table->boolean('is_administrator')->default(false)->after('is_fund_manager');
        });

        // 本番のシステム管理用アカウント(login_id=admin)にadministratorを付与する。
        DB::table('staff')->where('login_id', 'admin')->update(['is_administrator' => true]);
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['is_executive', 'is_fund_manager', 'is_administrator']);
        });
    }
};
