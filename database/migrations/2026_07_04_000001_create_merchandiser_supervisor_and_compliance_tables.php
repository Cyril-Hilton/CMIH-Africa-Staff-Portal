<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchandiser_pcm_clockins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kd_id')->constrained('key_distributors')->cascadeOnDelete();
            $table->dateTime('clocked_in_at');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('distance_from_kd', 8, 2);
            $table->string('status', 24)->default('verified');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('merchandiser_pjps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->date('week_start');
            $table->date('week_end')->nullable();
            $table->json('kd_ids')->nullable();
            $table->json('merchandiser_ids')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedInteger('radius_meters')->default(150);
            $table->string('file_path')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('forwarded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('merchandiser_pjp_clockins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjp_id')->constrained('merchandiser_pjps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('clocked_in_at');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('distance_from_pjp', 8, 2);
            $table->string('status', 24)->default('verified');
            $table->timestamps();
        });

        Schema::create('merchandiser_supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('merchandiser_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('kd_id')->nullable()->constrained('key_distributors')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('merchandiser_compliance_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24)->default('in_app');
            $table->string('subject');
            $table->text('message');
            $table->json('issues')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->boolean('sms_attempted')->default(false);
            $table->boolean('sms_sent')->default(false);
            $table->string('status', 24)->default('sent');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchandiser_compliance_queries');
        Schema::dropIfExists('merchandiser_supervisor_assignments');
        Schema::dropIfExists('merchandiser_pjp_clockins');
        Schema::dropIfExists('merchandiser_pjps');
        Schema::dropIfExists('merchandiser_pcm_clockins');
    }
};
