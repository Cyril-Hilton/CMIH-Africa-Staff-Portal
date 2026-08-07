<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('client_logo_path_2')->nullable()->after('client_brand_name');
            $table->string('client_brand_name_2')->nullable()->after('client_logo_path_2');
            $table->text('success_message')->nullable()->after('client_brand_name_2');
            $table->boolean('location_enabled')->default(false)->after('success_message');
            $table->string('location_url')->nullable()->after('location_enabled');
            $table->string('location_label')->nullable()->after('location_url');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn([
                'client_logo_path_2',
                'client_brand_name_2',
                'success_message',
                'location_enabled',
                'location_url',
                'location_label',
            ]);
        });
    }
};
