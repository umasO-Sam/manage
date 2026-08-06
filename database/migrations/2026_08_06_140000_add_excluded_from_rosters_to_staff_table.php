<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 名簿(担当者リスト)から除外するフラグ。
 *
 * テストアカウントや管理アカウントのような、実在の担当者ではないアカウントを
 * 作業日報一覧・勤務状況一覧・社内担当者リストなどに出さないためのもの。
 * ログインIDを直接除外する方式にしなかったのは、退職者が出たときに同じ問題が
 * 起きるため(コード修正とデプロイなしで運用側が対応できるようにする)。
 *
 * ＩＤ管理そのものからは除外しない。管理対象から消えると設定を戻せなくなる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('excluded_from_rosters')->default(false)->after('is_supervisor');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('excluded_from_rosters');
        });
    }
};
