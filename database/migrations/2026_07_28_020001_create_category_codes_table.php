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
        Schema::create('category_codes', function (Blueprint $table) {
            $table->id();
            // Access元データで分類コードが重複しているケースがあるためユニーク制約は付けない
            $table->unsignedInteger('code')->index();
            $table->string('major_category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('item_name')->nullable();
            $table->boolean('is_parts')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_outsourcing')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_codes');
    }
};
