<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 物件管理ボード用の項目。
 *
 * 受注ヘッダ(business_orders)とカードは1対1で対応する。受注そのものの属性は受注ヘッダに、
 * カンバン上の状態(ステージ)はカードに持たせる。
 * recipientは移行した過去分(取引先レコードを持たない文字列のみ)のために残し、
 * 新規はbusiness_partner_idで紐づける。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_orders', function (Blueprint $table) {
            $table->foreignId('business_partner_id')->nullable()->after('recipient')->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->after('business_partner_id')->constrained('staff')->nullOnDelete(); // 社内担当者
            $table->boolean('is_direct_delivery_only')->default(false)->after('staff_id'); // 直送部品のみ(社内の管理方法が変わるだけの目印)
            $table->boolean('invoice_confirmed')->default(false)->after('is_direct_delivery_only'); // 請求書PDFの代わりのチェック
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('business_order_id')->nullable()->after('order_number_id')->constrained()->nullOnDelete();
        });

        Schema::table('attachments', function (Blueprint $table) {
            // completion_proof(完了確認書・証跡) / delivery_note(納品書) / invoice(請求書)
            $table->string('kind', 30)->nullable()->after('card_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_order_id');
        });

        Schema::table('business_orders', function (Blueprint $table) {
            $table->dropColumn(['is_direct_delivery_only', 'invoice_confirmed']);
            $table->dropConstrainedForeignId('staff_id');
            $table->dropConstrainedForeignId('business_partner_id');
        });
    }
};
