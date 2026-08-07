<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandiser_visits', function (Blueprint $table) {
            $table->string('sku_entry_mode', 16)->default('manual')->after('hangers_available');
            $table->string('ai_detection_status', 32)->nullable()->after('sku_entry_mode');
            $table->string('ai_shelf_photo_path')->nullable()->after('ai_detection_status');
            $table->json('ai_detection_payload')->nullable()->after('ai_shelf_photo_path');
            $table->text('ai_detection_notes')->nullable()->after('ai_detection_payload');
        });
    }

    public function down(): void
    {
        Schema::table('merchandiser_visits', function (Blueprint $table) {
            $table->dropColumn([
                'sku_entry_mode',
                'ai_detection_status',
                'ai_shelf_photo_path',
                'ai_detection_payload',
                'ai_detection_notes',
            ]);
        });
    }
};
