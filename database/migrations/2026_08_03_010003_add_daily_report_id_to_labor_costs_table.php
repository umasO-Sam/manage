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
        Schema::table('labor_costs', function (Blueprint $table) {
            $table->foreignId('daily_report_id')->nullable()->constrained('daily_reports')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labor_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('daily_report_id');
        });
    }
};
