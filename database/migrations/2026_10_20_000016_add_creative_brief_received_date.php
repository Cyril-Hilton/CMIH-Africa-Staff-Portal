<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_consolidated_items', fn (Blueprint $t) => $t->date('brief_received_on')->nullable());
    }

    public function down(): void
    {
        Schema::table('weekly_consolidated_items', fn (Blueprint $t) => $t->dropColumn('brief_received_on'));
    }
};
