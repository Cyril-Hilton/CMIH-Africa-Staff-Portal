<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'project_lead_id')) {
                $table->foreignId('project_lead_id')->nullable()->after('client_name')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('campaigns', 'duration')) {
                $table->unsignedInteger('duration')->nullable()->after('location_brief');
            }

            if (! Schema::hasColumn('campaigns', 'status_update')) {
                $table->string('status_update')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'project_lead_id')) {
                $table->dropConstrainedForeignId('project_lead_id');
            }

            if (Schema::hasColumn('campaigns', 'duration')) {
                $table->dropColumn('duration');
            }

            if (Schema::hasColumn('campaigns', 'status_update')) {
                $table->dropColumn('status_update');
            }
        });
    }
};
