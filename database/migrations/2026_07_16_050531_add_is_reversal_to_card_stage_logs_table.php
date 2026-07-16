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
            $table->boolean('is_reversal')->default(false)->after('stage_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_stage_logs', function (Blueprint $table) {
            $table->dropColumn('is_reversal');
        });
    }
};
