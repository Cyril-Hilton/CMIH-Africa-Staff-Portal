<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('power_bi_report_links', function (Blueprint $table) {
            $table->id(); $table->string('title', 150); $table->text('url'); $table->char('url_hash', 64)->unique();
            $table->foreignId('created_by')->constrained('users'); $table->foreignId('updated_by')->constrained('users');
            $table->boolean('active')->default(true); $table->unsignedInteger('revision')->default(1); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('power_bi_report_links'); }
};
