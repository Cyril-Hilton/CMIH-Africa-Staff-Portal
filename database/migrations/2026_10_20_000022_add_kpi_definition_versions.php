<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_kpis', function (Blueprint $t) {
            $t->foreignId('supersedes_kpi_id')->nullable()->unique()->constrained('performance_kpis')->restrictOnDelete();
            $t->date('effective_from')->nullable();
            $t->date('effective_until')->nullable();
        });
        Schema::create('performance_kpi_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kpi_id')->constrained('performance_kpis')->restrictOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->json('changes');
            $t->date('effective_on');
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->text('review_notes')->nullable();
            $t->foreignId('new_kpi_id')->nullable()->constrained('performance_kpis')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_kpi_revisions');
        Schema::table('performance_kpis', function (Blueprint $t) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $t->dropUnique(['supersedes_kpi_id']);
                $t->dropConstrainedForeignId('supersedes_kpi_id');
            } else {
                $t->dropForeign(['supersedes_kpi_id']);
                $t->dropUnique(['supersedes_kpi_id']);
                $t->dropColumn('supersedes_kpi_id');
            }
            $t->dropColumn(['effective_from', 'effective_until']);
        });
    }
};
