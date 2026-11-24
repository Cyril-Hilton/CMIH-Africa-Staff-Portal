<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operations_handovers', function (Blueprint $table) {
            $table->dateTime('event_at')->nullable()->change();
            $table->dateTime('setup_due_at')->nullable()->change();
        });
    }
    public function down(): void
    {
        // Keep nullable dates on rollback: existing TBC records must never receive fabricated dates.
    }
};
