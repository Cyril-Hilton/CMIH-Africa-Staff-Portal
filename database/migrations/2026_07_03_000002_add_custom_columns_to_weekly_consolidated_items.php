<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_consolidated_items', function (Blueprint $table) {
            $table->json('supporting_roles')->nullable()->after('supporting_staff_ids');
            $table->json('custom_fields')->nullable()->after('notes');
        });

        Schema::create('weekly_consolidated_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('column_key');
            $table->string('label');
            $table->string('type')->default('rich_text');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'column_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_consolidated_columns');

        Schema::table('weekly_consolidated_items', function (Blueprint $table) {
            $table->dropColumn(['supporting_roles', 'custom_fields']);
        });
    }
};
