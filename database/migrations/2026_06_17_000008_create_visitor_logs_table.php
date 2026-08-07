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
        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->text('purpose')->nullable();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('status', 32)->default('pre_ticketed'); // 'pre_ticketed', 'checked_in', 'checked_out'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};
