<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operations_report_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('register_id')->nullable()->constrained('performance_registers')->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('state', 20)->index();
            $table->unsignedInteger('revision')->default(1);
            $table->string('source_version', 64)->nullable();
            $table->json('payload');
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::table('asset_warehouse_requests', function (Blueprint $table) {
            $table->foreignId('operations_activation_id')->nullable()->constrained('performance_registers')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_warehouse_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operations_activation_id');
            $table->dropConstrainedForeignId('campaign_id');
        });
        Schema::dropIfExists('operations_report_submissions');
    }
};
