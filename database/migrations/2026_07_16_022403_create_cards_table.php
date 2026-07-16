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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_type_id')->constrained()->restrictOnDelete();
            $table->string('order_no');
            $table->string('item_name');
            $table->string('manufacturer');
            $table->unsignedInteger('quantity');
            $table->date('due_date');
            $table->unsignedTinyInteger('current_stage')->default(0);
            $table->foreignId('created_by')->constrained('staff')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
