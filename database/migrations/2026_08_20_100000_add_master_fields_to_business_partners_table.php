<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 取引先(受注先)マスタに、これまでExcelの「売上取引先一覧」で管理していた項目を移す。
 *
 * 住所・TEL・処理方法などは1社に複数行入っていることがある(拠点が2つ、電話が2本など)ため、
 * 原文の改行をそのまま保持できるようtext型にしている。
 *
 * related_order_nos(関連注番)は、物件管理の受注先プルダウンを注番で絞り込むための欄。
 * 注番の形式が揃っていない過去分も入れられるよう、改行・読点区切りの自由入力にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->string('kana_group', 10)->nullable()->after('customer_code');   // 50音
            $table->text('postal_code')->nullable()->after('payment_terms');        // 郵便番号
            $table->text('address')->nullable()->after('postal_code');              // 住所
            $table->text('tel')->nullable()->after('address');                      // TEL
            $table->text('fax')->nullable()->after('tel');                          // FAX
            $table->text('handling_method')->nullable()->after('fax');              // 処理方法
            $table->string('yayoi_sub_account')->nullable()->after('handling_method'); // 弥生補助科目
            $table->string('dust_bag')->nullable()->after('yayoi_sub_account');     // 集塵機の袋
            $table->unsignedInteger('display_order')->nullable()->after('dust_bag'); // 並び順
            $table->text('remarks')->nullable()->after('display_order');            // 備考
            $table->text('subcontract_note')->nullable()->after('remarks');         // 下請法サイト60日伺い
            $table->text('related_order_nos')->nullable()->after('subcontract_note'); // 関連注番
        });
    }

    public function down(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropColumn([
                'kana_group', 'postal_code', 'address', 'tel', 'fax', 'handling_method',
                'yayoi_sub_account', 'dust_bag', 'display_order', 'remarks',
                'subcontract_note', 'related_order_nos',
            ]);
        });
    }
};
