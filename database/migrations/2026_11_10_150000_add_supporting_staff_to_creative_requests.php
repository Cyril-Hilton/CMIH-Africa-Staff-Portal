<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_requests', function (Blueprint $table) {
            $table->json('supporting_staff_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creative_requests', function (Blueprint $table) {
            $table->dropColumn('supporting_staff_ids');
        });
    }
};
