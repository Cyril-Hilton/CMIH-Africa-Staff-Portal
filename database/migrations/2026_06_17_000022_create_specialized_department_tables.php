<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // HR: Visitor Pre-Ticketing
        Schema::create('visitor_pre_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('visitor_name');
            $table->string('visitor_company')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('visitor_phone')->nullable();
            $table->text('purpose');
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('expected_arrival');
            $table->string('status', 20)->default('pending'); // pending, arrived, cancelled
            $table->timestamps();
        });

        // HR: Corporate Phone Directory
        Schema::create('phone_directory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('phone')->nullable();
            $table->string('extension', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('category', 20)->default('staff'); // staff, vendor, client, emergency
            $table->boolean('is_vendor')->default(false);
            $table->string('company')->nullable();
            $table->timestamps();
        });

        // Finance: Project Budgets
        Schema::create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('title');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('Draft'); // Draft, Submitted, Approved, Rejected
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Finance: Project Budget Line Items
        Schema::create('project_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('project_budgets')->cascadeOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('category')->nullable();
            $table->timestamps();
        });

        // Finance: Supplier Invoices
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->string('supplier_name');
            $table->text('description');
            $table->decimal('amount', 12, 2);
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('Pending'); // Pending, Approved, Rejected, Paid, Flagged
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Brands & Marketing: POSM / Field Materials Ledger
        Schema::create('posm_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('item_type', 50)->default('POSM'); // POSM, Uniform, Banner, Tablet, AV, Other
            $table->string('client_brand')->nullable();
            $table->integer('quantity_in')->default(0);
            $table->integer('quantity_out')->default(0);
            $table->integer('quantity_balance')->virtualAs('quantity_in - quantity_out');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Creative: Design Brief Comments & Proofing Threads
        Schema::create('creative_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->string('version_label', 20)->nullable(); // e.g. 'v1', 'v2-final'
            $table->string('status', 20)->default('feedback'); // feedback, approved, revision_requested
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_comments');
        Schema::dropIfExists('posm_ledgers');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('project_budget_items');
        Schema::dropIfExists('project_budgets');
        Schema::dropIfExists('phone_directory');
        Schema::dropIfExists('visitor_pre_tickets');
    }
};
