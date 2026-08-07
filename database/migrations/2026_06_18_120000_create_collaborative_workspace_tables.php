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
        Schema::create('collaborative_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('doc_type', 32); // 'budget' or 'document'
            $table->longText('content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('current_holder_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status', 32)->default('draft'); // 'draft', 'pending_manager', 'pending_finance', 'pending_cvo', 'approved', 'rejected'
            $table->timestamps();
        });

        Schema::create('document_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('collaborative_documents')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('permission', 32)->default('view'); // 'view' or 'edit'
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_collaborators');
        Schema::dropIfExists('collaborative_documents');
    }
};
