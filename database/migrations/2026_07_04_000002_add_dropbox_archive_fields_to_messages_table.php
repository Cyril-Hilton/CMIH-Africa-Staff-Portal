<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'original_attachment_path')) {
                $table->string('original_attachment_path')->nullable()->after('attachment_path');
            }

            if (! Schema::hasColumn('messages', 'dropbox_shared_url')) {
                $table->text('dropbox_shared_url')->nullable()->after('original_attachment_path');
            }

            if (! Schema::hasColumn('messages', 'dropbox_archived_at')) {
                $table->timestamp('dropbox_archived_at')->nullable()->after('dropbox_shared_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['dropbox_archived_at', 'dropbox_shared_url', 'original_attachment_path'] as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
