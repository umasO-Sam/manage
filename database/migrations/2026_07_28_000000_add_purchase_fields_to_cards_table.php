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
        Schema::table('cards', function (Blueprint $table) {
            $table->string('machine_number')->nullable()->after('order_number_id');
            $table->string('model_number')->nullable()->after('item_name');
            $table->string('due_date_type')->nullable()->after('due_date');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->date('due_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->date('due_date')->nullable(false)->change();
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['machine_number', 'model_number', 'due_date_type']);
        });
    }
};
