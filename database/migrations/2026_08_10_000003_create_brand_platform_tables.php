<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brand_activations')) {
            Schema::create('brand_activations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('activation_type')->nullable()->index();
                $table->string('status')->default('live')->index();
                $table->date('starts_at')->nullable()->index();
                $table->date('ends_at')->nullable()->index();
                $table->unsignedInteger('target_reach')->default(0);
                $table->unsignedInteger('actual_reach')->default(0);
                $table->json('locations')->nullable();
                $table->text('description')->nullable();
                $table->string('client_share_token')->nullable()->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('brand_staff_assignments')) {
            Schema::create('brand_staff_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role')->default('agency_staff')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['brand_id', 'user_id', 'role']);
            });
        }

        if (! Schema::hasTable('brand_consumer_entries')) {
            Schema::create('brand_consumer_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_activation_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name')->nullable();
                $table->string('phone')->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->string('age_band')->nullable();
                $table->string('gender')->nullable();
                $table->string('location')->nullable()->index();
                $table->string('source')->nullable()->index();
                $table->string('result_type')->nullable();
                $table->json('answers')->nullable();
                $table->timestamps();

                $table->index(['brand_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('brand_field_activities')) {
            Schema::create('brand_field_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_activation_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('staff_role')->default('supporting_staff')->index();
                $table->string('activity_type')->default('field_update')->index();
                $table->string('location')->nullable()->index();
                $table->unsignedInteger('units')->default(0);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->string('evidence_path')->nullable();
                $table->timestamps();

                $table->index(['brand_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_field_activities');
        Schema::dropIfExists('brand_consumer_entries');
        Schema::dropIfExists('brand_staff_assignments');
        Schema::dropIfExists('brand_activations');
    }
};
