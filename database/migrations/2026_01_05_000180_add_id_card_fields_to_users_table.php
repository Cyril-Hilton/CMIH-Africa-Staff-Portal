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
            $table->string('staff_id_number', 32)->nullable()->unique()->after('job_title');
            $table->string('position_title')->nullable()->after('staff_id_number');
            $table->date('date_of_birth')->nullable()->after('birthday_day');
            $table->date('id_expires_at')->nullable()->after('start_date');
            $table->timestamp('id_card_sent_at')->nullable()->after('id_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'staff_id_number',
                'position_title',
                'date_of_birth',
                'id_expires_at',
                'id_card_sent_at',
            ]);
        });
    }
};
