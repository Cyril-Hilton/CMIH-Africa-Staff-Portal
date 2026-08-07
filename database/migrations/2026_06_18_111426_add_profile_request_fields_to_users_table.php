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
        Schema::table('users', function (Blueprint $table) {
            $table->string('requested_position_title')->nullable()->after('position_title');
            $table->string('requested_department')->nullable()->after('department');
            $table->timestamp('requested_change_at')->nullable()->after('mute_sounds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['requested_position_title', 'requested_department', 'requested_change_at']);
        });
    }
};
