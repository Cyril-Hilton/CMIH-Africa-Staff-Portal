<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_policies', function (Blueprint $t) {
            $t->id();
            $t->json('rules');
            $t->date('effective_on');
            $t->string('effective_key')->nullable()->unique();
            $t->foreignId('submitted_by')->constrained('users');
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->string('status')->default('pending');
            $t->text('reason');
            $t->text('review_note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_policies');
    }
};
