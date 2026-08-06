<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 見積番号の取得ログ。誰がいつどの注番を採ったかをadministratorが後から追えるようにする。
 *
 * 注番そのもの(full_no)は台帳の行が直されても追跡できるよう、ログ側にも持たせる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_number_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_number_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_no')->index();
            $table->string('action', 20);                 // taken(取得) / updated(修正)
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();      // 操作した人
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete(); // 社内担当者
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_number_logs');
    }
};
