<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('type');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            // 有給休暇(paid_leave)専用: 1日/半日/時間単位の粒度と、時間単位取得時の時間数。
            $table->string('granularity')->nullable();
            $table->decimal('hours', 4, 1)->nullable();

            // 慶弔休暇/特別休暇/ボランティア休暇の事由区分と、忌引きの続柄・その他事由の自由記入。
            $table->string('reason_code')->nullable();
            $table->string('reason_detail')->nullable();

            // 申請日数。就業規則の号数から自動算出できるものは自動セットし、
            // 続柄による忌引き日数など別表を参照する必要があるものは自由入力とする。
            // 有給休暇の時間単位取得(2時間=0.25日)を正しく表せるよう小数点2桁とする。
            $table->decimal('day_count', 4, 2)->nullable();

            // 休日勤務申請/代休申請専用。
            $table->string('order_no')->nullable();
            $table->string('work_location')->nullable();
            $table->date('substitute_holiday_date')->nullable();
            $table->boolean('no_substitute_needed')->default(false);
            $table->decimal('actual_worked_hours', 4, 1)->nullable();
            $table->date('compensatory_date')->nullable();

            // 承認ワークフロー。「上長」に相当する既存フィールドが無いため、申請ごとに都度選ばせる。
            $table->foreignId('approver_id')->constrained('staff')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
