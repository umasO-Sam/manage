<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 見積番号(注文番号)台帳と客先番号マスタ。
 *
 * 形式: 客先番号(1〜3英字) + 見積単位(3桁) + '-' + 見積区分(1英字) + 見積通番(2桁) + 補足区分(1英字+2桁、最大3組)
 *   例) NP002-N02D07 / Q631-N01K01 / DH013-N01
 *
 * 過去の台帳(sample/order の23ファイル)は規約どおりでない行が多い
 * (見積単位が2桁、枝番 -A/-B/-C、廃止済みのD区分など)。そのため suffix は原文のまま保持し、
 * 採番候補の計算にだけ規約に沿ってパースできた行を使う。
 *
 * 物件管理ボードが使う注番マスタ(order_numbers)とは別テーブルにする。あちらは受注確定後の
 * 注番で形式チェックも別のため、見積段階の採番台帳と混ぜると相互に壊れる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // 客先番号
            $table->string('company_name');          // 客先会社名
            $table->timestamps();
        });

        Schema::create('quote_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('full_no')->index();          // 組み立てた見積番号(例 NP002-N02D07)
            $table->string('customer_code', 10)->index(); // 客先番号
            $table->string('unit_no', 10);                // 見積単位(原文。過去分は2桁のこともある)
            $table->string('suffix')->nullable();         // ハイフン以降(原文。N01 / N02D07 / N03-A など)
            $table->string('quote_type', 2)->nullable();  // 見積区分(N/F)。パースできた場合のみ
            $table->string('quote_seq', 4)->nullable();   // 見積通番。パースできた場合のみ
            $table->string('extra_code', 2)->nullable();  // 先頭の補足区分(T/K/S/B/H/D)
            $table->string('project_name')->nullable();   // 件名(工事名・製品名)
            $table->string('delivery_dest')->nullable();  // 納入先
            $table->string('customer_contact')->nullable(); // 客先担当者
            $table->text('remarks')->nullable();
            $table->string('note_no')->nullable();        // ノートNo(過去台帳の欄)
            $table->string('completed_on')->nullable();   // 完了日(S59.5/H17.12.19など和暦混在のため文字列)
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete(); // 社内担当者
            $table->string('source', 20)->default('manage')->index(); // legacy(過去台帳) / manage(この画面で採番)
            $table->timestamps();
        });

        Schema::table('business_partners', function (Blueprint $table) {
            // 客先番号が一致する取引先から会社名を引くための紐づけ。
            $table->string('customer_code', 10)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropUnique('business_partners_customer_code_unique');
            $table->dropColumn('customer_code');
        });

        Schema::dropIfExists('quote_numbers');
        Schema::dropIfExists('customer_codes');
    }
};
