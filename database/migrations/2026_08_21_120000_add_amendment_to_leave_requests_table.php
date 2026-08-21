<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 承認済みの休日勤務申請・代休申請の変更フロー。
 *
 * 出勤した日(start_date)は動かせないが、振替休日をいつ取るか・そもそも取らないか、
 * 代休をいつ取るかは後から変わる。取り消して出し直すと出勤の事実まで消えるため、
 * 「変更申請」として同じ申請のまま決裁をやり直せるようにする。
 *
 * 取消(cancel_status)と違い、変更中はstatusを承認待ちへ戻す。振替休日・代休日が
 * 未確定になるため、勤務状況一覧・カレンダーでも「承認待ち」に見えるのが正しい。
 * 新しい値は amend_* に預け、勤怠管理者が反映した時点で本体の列へ移す。
 *
 * 遷移:
 *   approved                        -- 本人が変更申請 --> amend_status=requested,          status=pending
 *   amend_status=requested          -- 上長が承認     --> amend_status=pending_reflection, status=pending_attendance
 *   amend_status=requested          -- 上長が差し戻し --> amend_status=null,               status=approved(元のまま)
 *   amend_status=pending_reflection -- 勤怠管理者が反映   --> 新しい値を本体へ、amend_status=null, status=approved
 *   amend_status=pending_reflection -- 勤怠管理者が差し戻し --> amend_status=null,          status=approved(元のまま)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('amend_status')->nullable()->after('cancelled_at');
            $table->text('amend_reason')->nullable()->after('amend_status');
            $table->text('amend_rejection_reason')->nullable()->after('amend_reason');
            $table->timestamp('amend_requested_at')->nullable()->after('amend_rejection_reason');
            $table->timestamp('amended_at')->nullable()->after('amend_requested_at');
            // 反映されるまでの新しい値。反映時に本体の列へ移し、ここは空に戻す。
            $table->date('amend_substitute_holiday_date')->nullable()->after('amended_at');
            $table->boolean('amend_no_substitute_needed')->nullable()->after('amend_substitute_holiday_date');
            $table->date('amend_compensatory_date')->nullable()->after('amend_no_substitute_needed');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'amend_status', 'amend_reason', 'amend_rejection_reason',
                'amend_requested_at', 'amended_at',
                'amend_substitute_holiday_date', 'amend_no_substitute_needed', 'amend_compensatory_date',
            ]);
        });
    }
};
