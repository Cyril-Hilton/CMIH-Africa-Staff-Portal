<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_report_packs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('frequency', 20);
            $t->date('period_start');
            $t->date('period_end');
            $t->json('payload');
            $t->string('sha256', 64);
            $t->timestamp('created_at');
            $t->unique(['user_id', 'frequency', 'period_start', 'period_end'], 'performance_pack_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_report_packs');
    }
};
