<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('assistance_type', 32);
            $table->string('vehicle_option', 80);
            $table->string('pickup_location');
            $table->string('destination');
            $table->date('requested_date');
            $table->time('requested_time')->nullable();
            $table->unsignedSmallInteger('passengers')->default(1);
            $table->text('purpose');
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('pending_hr');
            $table->text('hr_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_requests');
    }
};
