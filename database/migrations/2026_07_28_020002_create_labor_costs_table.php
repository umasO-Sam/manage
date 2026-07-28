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
        Schema::create('labor_costs', function (Blueprint $table) {
            $table->id();
            $table->date('work_date')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('order_no')->nullable()->index();
            $table->string('machine_no')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('work_hours')->default(0);
            $table->unsignedInteger('work_minutes')->default(0);
            $table->boolean('is_overtime')->default(false);
            $table->decimal('position_weight_cache', 5, 2)->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_provisional')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labor_costs');
    }
};
