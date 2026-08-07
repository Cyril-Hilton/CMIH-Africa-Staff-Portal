<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('cmih_logo_path')->nullable()->after('settings');
            $table->string('client_logo_path')->nullable()->after('cmih_logo_path');
            $table->string('client_brand_name')->nullable()->after('client_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['cmih_logo_path', 'client_logo_path', 'client_brand_name']);
        });
    }
};
