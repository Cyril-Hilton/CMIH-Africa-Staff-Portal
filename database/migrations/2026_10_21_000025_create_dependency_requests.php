<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_dependency_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('upstream_id')->constrained('weekly_consolidated_items');
            $t->foreignId('downstream_id')->constrained('weekly_consolidated_items');
            $t->foreignId('submitted_by')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->text('impact');
            $t->text('review_note')->nullable();
            $t->string('status')->default('pending');
            $t->timestamps();
            $t->unique(['upstream_id', 'downstream_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_dependency_requests');
    }
};
