<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_weekly_reports', function (Blueprint $t) {
            $t->id();
            $t->date('period_start');
            $t->date('period_end');
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('submitted');
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['period_start', 'period_end', 'prepared_by']);
        });
        Schema::create('admin_weekly_report_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('admin_weekly_reports')->restrictOnDelete();
            $t->string('section', 30);
            $t->string('code', 50);
            $t->string('value_state', 20);
            $t->decimal('numeric_value', 14, 4)->nullable();
            $t->text('text_value')->nullable();
            $t->string('status')->nullable();
            $t->text('issue')->nullable();
            $t->text('action')->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->date('due_date')->nullable();
            $t->timestamps();
            $t->unique(['report_id', 'section', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_weekly_report_lines');
        Schema::dropIfExists('admin_weekly_reports');
    }
};
