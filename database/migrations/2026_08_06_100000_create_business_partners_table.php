<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 取引先(受注先)マスタ。物件管理ボードのカード作成時に受注先名だけの仮登録として作られ、
 * 資金管理者が銀行・取引区分・締め日・支払条件を入力して「取引条件調整完了」を押すと本登録になる。
 *
 * 「取引条件調整中」バッジはカードではなくこのレコードの状態から導出する。
 * 同じ新規取引先で2枚目のカードを作った場合も、取引先が仮登録のままなら両方のカードに
 * バッジが出て請求済へ進めない、という整合を自動で取るため。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('bank')->nullable();              // 銀行
            $table->string('transaction_type')->nullable();  // 取引区分
            $table->string('closing_day')->nullable();       // 締め日
            $table->string('payment_terms')->nullable();     // 支払い条件
            $table->boolean('is_provisional')->default(true)->index(); // 仮登録(取引条件調整中)
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partners');
    }
};
