<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_warehouse_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('requested_quantity')->default(1);
            $table->date('requested_for')->nullable();
            $table->string('destination_location')->nullable();
            $table->string('status')->default('pending_check_approval')->index();
            $table->text('purpose');
            $table->text('requester_notes')->nullable();
            $table->text('review_note')->nullable();
            $table->text('issue_note')->nullable();
            $table->text('return_note')->nullable();
            $table->string('pre_use_image_path')->nullable();
            $table->string('issue_image_path')->nullable();
            $table->string('return_image_path')->nullable();
            $table->timestamp('approved_to_check_at')->nullable();
            $table->timestamp('approved_for_use_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_warehouse_requests');
    }
};
