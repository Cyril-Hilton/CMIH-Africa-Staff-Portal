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
            $table->string('job_level', 32)->default('executive')->after('access_role'); // 'super_admin', 'manager', 'executive', 'promoter'
            $table->json('permissions_matrix')->nullable()->after('job_level');
            $table->date('contract_expires_at')->nullable()->after('id_expires_at');
            $table->foreignId('line_manager_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->integer('leave_balance')->default(30)->after('status');
            
            // Sensitive Details (HR & Finance Lock)
            $table->text('residential_address')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            $table->string('next_of_kin_relation')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('momo_number')->nullable();
            $table->string('momo_name')->nullable();
            $table->string('ssnit_number')->nullable();
            $table->string('ghana_card_number')->nullable();
            $table->string('ghana_card_path')->nullable();

            // Promoter Details
            $table->string('tshirt_size', 8)->nullable();
            $table->decimal('height', 4, 2)->nullable();
            $table->string('languages_spoken')->nullable();
            $table->string('operational_city')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['line_manager_id']);
            $table->dropColumn([
                'line_manager_id',
                'job_level',
                'permissions_matrix',
                'contract_expires_at',
                'leave_balance',
                'residential_address',
                'next_of_kin_name',
                'next_of_kin_phone',
                'next_of_kin_relation',
                'bank_name',
                'bank_branch',
                'bank_account_name',
                'bank_account_number',
                'momo_number',
                'momo_name',
                'ssnit_number',
                'ghana_card_number',
                'ghana_card_path',
                'tshirt_size',
                'height',
                'languages_spoken',
                'operational_city'
            ]);
        });
    }
};
