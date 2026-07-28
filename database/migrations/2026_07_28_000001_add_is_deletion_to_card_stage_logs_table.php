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
        Schema::table('card_stage_logs', function (Blueprint $table) {
            $table->boolean('is_deletion')->default(false)->after('is_reversal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_stage_logs', function (Blueprint $table) {
            $table->dropColumn('is_deletion');
        });
    }
};
