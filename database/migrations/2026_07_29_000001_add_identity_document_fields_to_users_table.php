<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ghana_card_front_path')) {
                $table->string('ghana_card_front_path')->nullable()->after('ghana_card_path');
            }

            if (! Schema::hasColumn('users', 'ghana_card_back_path')) {
                $table->string('ghana_card_back_path')->nullable()->after('ghana_card_front_path');
            }

            if (! Schema::hasColumn('users', 'passport_number')) {
                $table->string('passport_number')->nullable()->after('ghana_card_back_path');
            }

            if (! Schema::hasColumn('users', 'passport_photo_path')) {
                $table->string('passport_photo_path')->nullable()->after('passport_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'ghana_card_front_path',
                'ghana_card_back_path',
                'passport_number',
                'passport_photo_path',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
