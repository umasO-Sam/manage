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
        Schema::create('purchase_details', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->index();
            $table->string('machine_no')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('item_name')->nullable();
            $table->string('dimensions')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('required_qty', 12, 2)->nullable();
            $table->string('usage_purpose')->nullable();
            $table->decimal('order_qty', 12, 2)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('stock_qty', 12, 2)->nullable();
            $table->string('supplier_name')->nullable()->index();
            $table->date('order_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('recipient')->nullable();
            $table->date('order_received_date')->nullable();
            $table->string('delivery_dest')->nullable();
            $table->decimal('order_amount', 14, 2)->nullable();
            $table->string('supplier_invoice_no')->nullable();
            $table->boolean('is_provisional')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
    }
};
