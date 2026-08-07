<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandiser_reports', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('label')->nullable();           // e.g. "Unilever Weekly Review"
            $table->json('sections_config');               // which sections to show/hide
            $table->timestamp('expires_at');               // 24 hours from creation
            $table->boolean('is_revoked')->default(false); // manual revoke
            $table->integer('view_count')->default(0);     // how many times viewed
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandiser_reports');
    }
};
