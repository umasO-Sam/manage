<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受注(物件)のヘッダ。注番につき1件。
 *
 * これまで受注先・納入先・受注日・受注金額は仕入の明細行(purchase_details)に相乗りしており、
 * 「1注番につき金額を持つ行は1つ」という暗黙のルールを人手で守る前提になっていた。
 * 実際に本番では同一注番で金額が食い違う注番が7件あり、原価計算・原価一覧は
 * MAX(order_amount)で拾う一方、見積補助だけがSUMで拾っていたため、行が増えると
 * 見積補助の売上金額だけが静かに二重になる状態だった。
 *
 * 受注は1件・明細はN件という実態どおりにヘッダを分離し、集計は全てこのテーブルを参照する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();          // 注番(purchase_details.item_codeと対応)
            $table->string('product_name')->nullable();    // 件名(製品名)
            $table->string('recipient')->nullable();       // 受注先
            $table->string('delivery_dest')->nullable();   // 納入先
            $table->date('order_received_date')->nullable()->index(); // 受注日
            $table->decimal('order_amount', 14, 2)->nullable();       // 受注金額
            $table->date('sales_date')->nullable();        // 売上日
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_orders');
    }
};
