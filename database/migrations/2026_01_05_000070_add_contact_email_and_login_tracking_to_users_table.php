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
            $table->string('contact_email')->unique()->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->string('last_login_user_agent', 255)->nullable()->after('last_login_ip');
            $table->boolean('must_reset_password')->default(false)->after('last_login_user_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'contact_email',
                'last_login_at',
                'last_login_ip',
                'last_login_user_agent',
                'must_reset_password',
            ]);
        });
    }
};
