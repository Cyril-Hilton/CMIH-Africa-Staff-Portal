<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_periodic_reports', function (Blueprint $t) {
            $t->id();
            $t->string('frequency', 10);
            $t->date('period_start');
            $t->date('period_end');
            $t->date('prepared_on');
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('compared_report_id')->nullable()->constrained('hr_periodic_reports')->restrictOnDelete();
            $t->string('status')->default('draft');
            $t->json('section_states');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->text('review_notes')->nullable();
            $t->timestamps();
            $t->unique(['frequency', 'period_start', 'period_end', 'prepared_by']);
        });
        Schema::create('hr_report_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('hr_periodic_reports')->restrictOnDelete();
            $t->string('section', 40);
            $t->json('data');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_report_entries');
        Schema::dropIfExists('hr_periodic_reports');
    }
};
