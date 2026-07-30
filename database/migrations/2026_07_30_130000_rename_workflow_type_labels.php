<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('workflow_types')->where('slug', 'purchase')->update(['name' => '購入手配']);
        DB::table('workflow_types')->where('slug', 'estimate')->update(['name' => '見積依頼']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('workflow_types')->where('slug', 'purchase')->update(['name' => '購入部品手配']);
        DB::table('workflow_types')->where('slug', 'estimate')->update(['name' => '見積り依頼']);
    }
};
