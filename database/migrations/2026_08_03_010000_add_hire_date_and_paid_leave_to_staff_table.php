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
        Schema::table('staff', function (Blueprint $table) {
            $table->date('hire_date')->nullable();
            $table->decimal('paid_leave_granted_current_year', 4, 1)->nullable();
            $table->decimal('paid_leave_granted_last_year', 4, 1)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['hire_date', 'paid_leave_granted_current_year', 'paid_leave_granted_last_year']);
        });
    }
};
