<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['performance_observations', 'performance_registers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['performance_observations', 'performance_registers'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('submitted_by'));
        }
    }
};
