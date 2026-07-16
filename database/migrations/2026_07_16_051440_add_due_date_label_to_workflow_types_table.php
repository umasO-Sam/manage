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
            $table->string('due_date_label')->default('希望納期')->after('name');
            $table->string('icon')->default('shopping-cart')->after('due_date_label');
            $table->boolean('allows_reference_order_no')->default(false)->after('icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_types', function (Blueprint $table) {
            $table->dropColumn(['due_date_label', 'icon', 'allows_reference_order_no']);
        });
    }
};
