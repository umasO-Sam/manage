<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 有給休暇の付与日数を0.25日単位で保持できるようにする。
 *
 * decimal(4,1)で作ってしまったため、2時間有休の単位である0.25日が
 * MySQL側で四捨五入され、14.25日が14.3日として保存されていた
 * (SQLiteは桁数を強制しないため開発環境では再現しない)。
 */
return new class extends Migration
{
    private const COLUMNS = ['paid_leave_granted_current_year', 'paid_leave_granted_last_year'];

    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('paid_leave_granted_current_year', 5, 2)->nullable()->change();
            $table->decimal('paid_leave_granted_last_year', 5, 2)->nullable()->change();
        });

        // 丸められて保存された値を戻す。0.25日単位を小数第1位に四捨五入すると
        // x.25 → x.3、x.75 → x.8 にしかならないため、この2つは元の値を一意に復元できる。
        foreach (self::COLUMNS as $column) {
            foreach (DB::table('staff')->whereNotNull($column)->pluck($column, 'id') as $id => $value) {
                $hundredths = (int) round(((float) $value - floor((float) $value)) * 100);

                $restored = match ($hundredths) {
                    30 => floor((float) $value) + 0.25,
                    80 => floor((float) $value) + 0.75,
                    default => null,
                };

                if ($restored !== null) {
                    DB::table('staff')->where('id', $id)->update([$column => $restored]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->decimal('paid_leave_granted_current_year', 4, 1)->nullable()->change();
            $table->decimal('paid_leave_granted_last_year', 4, 1)->nullable()->change();
        });
    }
};
