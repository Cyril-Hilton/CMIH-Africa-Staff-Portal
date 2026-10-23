<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_account_targets', function (Blueprint $t) {
            $t->unsignedInteger('revision')->default(1);
            $t->json('proposed_correction')->nullable();
            $t->foreignId('correction_submitted_by')->nullable()->constrained('users');
            $t->text('correction_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_account_targets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('correction_submitted_by');
            $t->dropColumn(['revision', 'proposed_correction', 'correction_reason']);
        });
    }
};
