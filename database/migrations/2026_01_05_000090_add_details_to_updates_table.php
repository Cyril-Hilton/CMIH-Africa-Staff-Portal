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
        Schema::table('updates', function (Blueprint $table) {
            $table->string('priority', 16)->default('medium')->after('status');
            $table->text('notes')->nullable()->after('summary');
            $table->string('timeline', 255)->nullable()->after('progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('updates', function (Blueprint $table) {
            $table->dropColumn(['priority', 'notes', 'timeline']);
        });
    }
};
