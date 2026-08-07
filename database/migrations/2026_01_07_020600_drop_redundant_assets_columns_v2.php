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
            // Safely drop created_by FK if column exists
            if (Schema::hasColumn('assets', 'created_by')) {
                // Try dropping FK, might fail if constraint name is different or missing, 
                // but checking column first helps.
                try {
                    $table->dropForeign(['created_by']);
                } catch (\Exception $e) {
                    // Ignore FK drop fail if it doesn't exist
                }
                $table->dropColumn('created_by');
            }

            // Safely drop asset_type if exists
            if (Schema::hasColumn('assets', 'asset_type')) {
                $table->dropColumn('asset_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'asset_type')) {
                $table->string('asset_type', 32)->default('Hardware');
            }
            if (!Schema::hasColumn('assets', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users');
            }
        });
    }
};
