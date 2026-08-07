<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_consolidated_items', function (Blueprint $table) {
            $table->id();
            $table->string('department')->index();
            $table->date('week_start')->index();
            $table->date('week_end')->nullable();
            $table->string('client_name')->nullable();
            $table->string('campaign_name')->nullable();
            $table->foreignId('lead_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('supporting_staff_ids')->nullable();
            $table->text('deliverables');
            $table->text('target_breakdown')->nullable();
            $table->text('achieved_breakdown')->nullable();
            $table->text('gap_breakdown')->nullable();
            $table->string('status')->default('Planned')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_consolidated_items');
    }
};
