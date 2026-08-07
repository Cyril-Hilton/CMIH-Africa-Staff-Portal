<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            if (! Schema::hasColumn('outlets', 'registered_by')) {
                $table->foreignId('registered_by')
                    ->nullable()
                    ->after('kd_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            if (Schema::hasColumn('outlets', 'registered_by')) {
                $table->dropConstrainedForeignId('registered_by');
            }
        });
    }
};
