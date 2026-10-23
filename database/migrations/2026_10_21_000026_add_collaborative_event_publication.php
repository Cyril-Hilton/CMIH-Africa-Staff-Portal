<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $t) {
            $t->string('event_key', 64)->nullable()->unique();
            $t->string('flyer_sha256', 64)->nullable()->unique();
            $t->string('original_filename')->nullable();
            $t->string('filename_key')->nullable()->index();
            $t->json('flyer_fingerprint')->nullable();
            $t->string('image_disk')->default('public');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('event_publication_locks', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->unsignedInteger('version')->default(0);
        });
        DB::table('event_publication_locks')->insert(['id' => 1, 'version' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_publication_locks');
        Schema::table('events', function (Blueprint $t) {
            $t->dropConstrainedForeignId('updated_by');
            $t->dropUnique(['event_key']);
            $t->dropUnique(['flyer_sha256']);
            $t->dropIndex(['filename_key']);
            $t->dropColumn(['event_key', 'flyer_sha256', 'original_filename', 'filename_key', 'flyer_fingerprint', 'image_disk', 'revision']);
        });
    }
};
