<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'merchandiser_daily_outlet_target')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('merchandiser_daily_outlet_target')
                ->nullable()
                ->default(null)
                ->change();
        });

        DB::table('users')
            ->where('access_role', 'merchandiser')
            ->where('merchandiser_daily_outlet_target', 8)
            ->update(['merchandiser_daily_outlet_target' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'merchandiser_daily_outlet_target')) {
            return;
        }

        DB::table('users')
            ->where('access_role', 'merchandiser')
            ->whereNull('merchandiser_daily_outlet_target')
            ->update(['merchandiser_daily_outlet_target' => 8]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('merchandiser_daily_outlet_target')
                ->default(8)
                ->nullable(false)
                ->change();
        });
    }
};
