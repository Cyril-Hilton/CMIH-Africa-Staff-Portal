<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_asset_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('source_reference')->nullable();
            $table->foreignId('activation_id')->nullable()->constrained('performance_registers')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->json('reported');
            $table->json('review')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_asset_suggestions');
    }
};
