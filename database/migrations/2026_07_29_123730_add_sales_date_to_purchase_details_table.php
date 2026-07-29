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
        Schema::table('purchase_details', function (Blueprint $table) {
            // 受注日(order_received_date)とは別に、実際に売上として計上した日付。
            // 受注金額が記載されているレコードに対し、売り上がったタイミングで手動登録する想定。
            $table->date('sales_date')->nullable()->after('order_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn('sales_date');
        });
    }
};
