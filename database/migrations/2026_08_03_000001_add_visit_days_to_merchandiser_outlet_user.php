<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandiser_outlet_user', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandiser_outlet_user', 'visit_days')) {
                $table->json('visit_days')->nullable()->after('assigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchandiser_outlet_user', function (Blueprint $table) {
            if (Schema::hasColumn('merchandiser_outlet_user', 'visit_days')) {
                $table->dropColumn('visit_days');
            }
        });
    }
};
