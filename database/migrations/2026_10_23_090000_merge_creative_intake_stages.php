<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('creative_requests')) return;
        // Preserve receipt dates, ownership and the immutable event history.
        DB::table('creative_requests')->where('stage', 'backlog')->update(['stage' => 'brief_received']);
        DB::table('creative_requests')->where('stage', 'changes_requested')->update(['stage' => 'in_progress']);
    }

    public function down(): void
    {
        // The prior release supports both resulting stages. Do not guess which
        // merged briefs used to be backlog or undo subsequent staff progress.
    }
};
