<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 休日勤務申請に勤怠管理者の承認段階を足す。
 *
 * 上長が承認した時点では status を 'pending_attendance'(承認待ちのまま)にし、
 * 勤怠管理者が確認して初めて 'approved' にする。いつ上長が承認したかは
 * 勤怠管理者の判断材料になるため別に持つ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->timestamp('supervisor_approved_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('supervisor_approved_at');
        });
    }
};
