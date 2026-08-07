<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_consolidated_items', function (Blueprint $table) {
            $table->string('priority', 50)->nullable()->after('status');
            $table->unsignedTinyInteger('progress_percent')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_consolidated_items', function (Blueprint $table) {
            $table->dropColumn(['priority', 'progress_percent']);
        });
    }
};
