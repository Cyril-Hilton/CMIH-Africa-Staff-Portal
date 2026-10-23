<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_account_targets', function (Blueprint $t) {
            $t->date('period_start')->nullable()->change();
            $t->date('period_end')->nullable()->change();
            $t->decimal('target_amount', 18, 2)->nullable()->change();
            $t->string('source_period_label')->nullable();
            $t->boolean('needs_reconciliation')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('crm_account_targets', fn (Blueprint $t) => $t->dropColumn(['source_period_label', 'needs_reconciliation'])); /* Nullable historical values are retained safely on rollback. */
    }
};
