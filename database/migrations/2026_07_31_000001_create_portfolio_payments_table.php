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
        Schema::create('portfolio_payments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('item'); // e.g. company_profile, design_brief, mockup, buy_a_plan, buy_a_catalogue
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('GHS');
            $table->string('reference')->unique();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->text('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_payments');
    }
};
