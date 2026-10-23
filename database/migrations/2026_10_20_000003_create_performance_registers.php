<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_registers', function (Blueprint $t) {
            $t->id();
            $t->string('department')->index();
            $t->string('kind', 40);
            $t->string('reference');
            $t->string('title');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('team')->nullable();
            $t->string('client')->nullable();
            $t->string('project')->nullable();
            $t->string('cost_center')->nullable();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status', 30);
            $t->json('data');
            $t->text('evidence_url')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['department', 'kind', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_registers');
    }
};
