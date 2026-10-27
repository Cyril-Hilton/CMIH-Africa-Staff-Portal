<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('posm_ledgers', function(Blueprint $table) {
        $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
        $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
        $table->date('occurred_on')->nullable()->index();
    }); }
    public function down(): void { Schema::table('posm_ledgers', function(Blueprint $table) {
        $table->dropConstrainedForeignId('asset_id');$table->dropConstrainedForeignId('campaign_id');$table->dropColumn('occurred_on');
    }); }
};
