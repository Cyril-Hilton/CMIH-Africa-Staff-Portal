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
        Schema::table('assets', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('type')->after('description'); // effectively replaces asset_type
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete()->after('image_path'); // effectively replaces created_by
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropColumn(['description', 'type', 'added_by']);
        });
    }
};
