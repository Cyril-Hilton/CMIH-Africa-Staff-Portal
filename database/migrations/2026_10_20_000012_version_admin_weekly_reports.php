<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_weekly_reports', fn (Blueprint $t) => $t->unsignedInteger('revision')->default(1));
    }

    public function down(): void
    {
        Schema::table('admin_weekly_reports', fn (Blueprint $t) => $t->dropColumn('revision'));
    }
};
