<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * レコードの出所(legacy = Access「仕入管理ＤＢ.accdb」由来 / manage = このアプリで登録)を持たせる。
 *
 * app:import-legacy-purchasing-data は取り込みのたびに purchase_details / labor_costs を
 * 無条件で全削除していたため、データ入力画面や作業日報からこのアプリで登録したレコードまで
 * 巻き添えで消える状態だった。以後は source='legacy' の行だけを洗い替える。
 *
 * 既存行はすべて legacy として扱う(直近の取り込みで全件が入れ替わっており、
 * 今それらを manage 扱いにすると次回の取り込みで重複が残ってしまうため)。
 * 新規行の既定は manage で、取り込みコマンドだけが明示的に legacy を入れる。
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_details', 'labor_costs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('source', 20)->default('manage')->index();
            });

            DB::table($table)->update(['source' => 'legacy']);
        }
    }

    public function down(): void
    {
        foreach (['purchase_details', 'labor_costs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('source');
            });
        }
    }
};
