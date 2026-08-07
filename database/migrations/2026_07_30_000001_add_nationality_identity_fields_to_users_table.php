<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'nationality_code')) {
                $table->string('nationality_code', 2)->nullable()->after('ssnit_number');
            }

            if (! Schema::hasColumn('users', 'identity_document_type')) {
                $table->string('identity_document_type', 20)->nullable()->after('nationality_code');
            }

            if (! Schema::hasColumn('users', 'national_id_type')) {
                $table->string('national_id_type', 100)->nullable()->after('identity_document_type');
            }

            if (! Schema::hasColumn('users', 'national_id_number')) {
                $table->string('national_id_number', 100)->nullable()->after('national_id_type');
            }

            if (! Schema::hasColumn('users', 'national_id_front_path')) {
                $table->string('national_id_front_path')->nullable()->after('national_id_number');
            }

            if (! Schema::hasColumn('users', 'national_id_back_path')) {
                $table->string('national_id_back_path')->nullable()->after('national_id_front_path');
            }
        });

        DB::table('users')
            ->whereNotNull('ghana_card_number')
            ->update([
                'nationality_code' => 'GH',
                'identity_document_type' => 'national_id',
                'national_id_type' => 'Ghana Card',
            ]);

        DB::table('users')
            ->whereNotNull('ghana_card_number')
            ->orderBy('id')
            ->eachById(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'national_id_number' => $user->ghana_card_number,
                    'national_id_front_path' => $user->ghana_card_front_path ?: $user->ghana_card_path,
                    'national_id_back_path' => $user->ghana_card_back_path,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'nationality_code',
                'identity_document_type',
                'national_id_type',
                'national_id_number',
                'national_id_front_path',
                'national_id_back_path',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
