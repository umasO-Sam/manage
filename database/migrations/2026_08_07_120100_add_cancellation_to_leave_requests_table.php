<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 承認済み申請の取消フロー。
 *
 * statusを増やさず cancel_status を別に持つ。勤務状況一覧・個人カレンダー・
 * 有給残日数はいずれも status が approved かどうかで判定しており、取消手続き中の
 * 申請をそれらから消してしまうと「取り消せるかまだ分からないのに休みが消える」
 * ことになるため。取消が確定した時点で初めて status を cancelled にする。
 *
 * 遷移:
 *   approved                      -- 本人が取消申請 -->  cancel_status=requested
 *   cancel_status=requested       -- 上長が承認     -->  cancel_status=pending_reflection
 *   cancel_status=requested       -- 上長が差し戻し -->  cancel_status=null (承認済みのまま)
 *   cancel_status=pending_reflection -- 勤怠管理者が反映   --> status=cancelled
 *   cancel_status=pending_reflection -- 勤怠管理者が差し戻し --> cancel_status=null (承認済みのまま)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('cancel_status')->nullable()->after('rejection_reason');
            $table->text('cancel_reason')->nullable()->after('cancel_status');
            $table->text('cancel_rejection_reason')->nullable()->after('cancel_reason');
            $table->timestamp('cancel_requested_at')->nullable()->after('cancel_rejection_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'cancel_status', 'cancel_reason', 'cancel_rejection_reason',
                'cancel_requested_at', 'cancelled_at',
            ]);
        });
    }
};
