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
        Schema::rename('users', 'staff');

        Schema::table('staff', function (Blueprint $table) {
            $table->string('department')->after('name');
            $table->string('login_id')->unique()->after('department');
            $table->boolean('is_procurement_manager')->default(false)->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['department', 'login_id', 'is_procurement_manager']);
        });

        Schema::rename('staff', 'users');
    }
};
