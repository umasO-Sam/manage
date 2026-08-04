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
        Schema::table('leave_requests', function (Blueprint $table) {
            // 慶弔休暇(忌引き)専用。花・電報の手配に必要な情報の任意入力欄。
            $table->string('funeral_venue_address')->nullable();
            $table->string('funeral_venue_phone')->nullable();
            $table->dateTime('wake_datetime')->nullable();
            $table->dateTime('funeral_datetime')->nullable();
            $table->boolean('flowers_declined')->default(false);
            $table->boolean('telegram_declined')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'funeral_venue_address', 'funeral_venue_phone',
                'wake_datetime', 'funeral_datetime',
                'flowers_declined', 'telegram_declined',
            ]);
        });
    }
};
