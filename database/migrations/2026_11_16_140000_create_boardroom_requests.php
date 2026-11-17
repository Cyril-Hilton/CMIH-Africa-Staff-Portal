<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boardroom_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requester_name');
            $table->text('purpose');
            $table->unsignedSmallInteger('attendees');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 24)->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->string('accessed_by_name')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('room_condition', 24)->nullable();
            $table->text('condition_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('boardroom_calendar_lock', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        DB::table('boardroom_calendar_lock')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('boardroom_calendar_lock');
        Schema::dropIfExists('boardroom_requests');
    }
};
