<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 勤怠管理者が本人に代わって休暇・休出を申請できるようにする。
 * 作業日報の代理提出(daily_reports.proxy_staff_id)と同じ持ち方。
 * 申請そのものは本人(staff_id)のものとして扱い、誰が代理で出したかだけをここに残す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('proxy_staff_id')->nullable()->after('staff_id')
                ->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proxy_staff_id');
        });
    }
};
