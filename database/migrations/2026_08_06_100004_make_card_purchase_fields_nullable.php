<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 数量・単位・希望納期を null 許容にする。
 *
 * これらは購入手配・見積依頼ボード固有の項目で、物件管理ボードのカード
 * (受注1件＝カード1枚)には存在しない。入力必須かどうかは各ボードの
 * リクエスト側で検証しているため、テーブル制約は緩めてよい。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->change();
            $table->string('unit')->nullable()->change();
            $table->date('due_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable(false)->change();
            $table->string('unit')->nullable(false)->change();
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
