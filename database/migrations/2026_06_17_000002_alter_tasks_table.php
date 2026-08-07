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
        // Alter tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('id')->constrained('campaigns')->nullOnDelete();
            $table->string('client_name')->nullable()->after('campaign_id');
            $table->json('supporting_staff_ids')->nullable()->after('assigned_to');
            $table->text('supporting_roles')->nullable()->after('supporting_staff_ids');
            $table->text('notes_feedback')->nullable()->after('priority');
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
        });

        // Drop updates table since updates are merged into tasks
        Schema::dropIfExists('updates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate updates table
        Schema::create('updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->string('status', 32)->default('on_track');
            $table->string('priority', 16)->default('medium');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('timeline', 255)->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn(['campaign_id', 'client_name', 'supporting_staff_ids', 'supporting_roles', 'notes_feedback', 'progress']);
        });
    }
};
