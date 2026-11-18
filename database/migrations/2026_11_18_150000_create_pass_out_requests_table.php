<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('pass_out_requests', function(Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('hod_id')->constrained('users')->restrictOnDelete();
            $table->date('request_date');
            $table->time('departure_time');
            $table->text('reason');
            $table->string('status', 32)->default('pending_supervisor');
            $table->string('reservation_key')->nullable()->unique();
            $table->json('history');
            $table->text('hr_notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'request_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('pass_out_requests'); }
};
