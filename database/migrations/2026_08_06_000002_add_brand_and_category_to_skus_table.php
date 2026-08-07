<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('name')->constrained('brands')->nullOnDelete();
            $table->string('category')->nullable()->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('category');
        });
    }
};
