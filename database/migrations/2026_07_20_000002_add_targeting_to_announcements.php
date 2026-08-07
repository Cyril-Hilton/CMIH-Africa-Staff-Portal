<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'audience_type')) {
                $table->string('audience_type', 24)->default('all')->after('pinned');
            }

            if (! Schema::hasColumn('announcements', 'recipient_ids')) {
                $table->json('recipient_ids')->nullable()->after('audience_type');
            }

            if (! Schema::hasColumn('announcements', 'department_keys')) {
                $table->json('department_keys')->nullable()->after('recipient_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            foreach (['audience_type', 'recipient_ids', 'department_keys'] as $column) {
                if (Schema::hasColumn('announcements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
