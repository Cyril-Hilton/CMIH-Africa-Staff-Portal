<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_printing_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->nullable()->unique();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->json('information');
            $t->json('items');
            $t->json('vendor');
            $t->json('checklist');
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_printing_requests');
    }
};
