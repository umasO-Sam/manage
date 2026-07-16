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
        Schema::table('workflow_types', function (Blueprint $table) {
            $table->dropColumn('allows_reference_order_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_types', function (Blueprint $table) {
            $table->boolean('allows_reference_order_no')->default(false)->after('icon');
        });
    }
};
