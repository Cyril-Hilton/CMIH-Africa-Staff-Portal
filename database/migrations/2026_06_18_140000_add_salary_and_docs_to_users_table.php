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
            $table->decimal('salary', 12, 2)->default(0.00)->after('job_level');
            $table->string('contract_path')->nullable()->after('salary');
            $table->string('job_description_path')->nullable()->after('contract_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['salary', 'contract_path', 'job_description_path']);
        });
    }
};
