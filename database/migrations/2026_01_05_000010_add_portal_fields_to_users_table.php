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
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('access_role', 32)->default('staff')->after('password');
            $table->string('status', 32)->default('pending')->after('access_role');
            $table->string('job_title')->nullable()->after('status');
            $table->unsignedTinyInteger('birthday_month')->nullable()->after('job_title');
            $table->unsignedTinyInteger('birthday_day')->nullable()->after('birthday_month');
            $table->date('start_date')->nullable()->after('birthday_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'access_role',
                'status',
                'job_title',
                'birthday_month',
                'birthday_day',
                'start_date',
            ]);
        });
    }
};
