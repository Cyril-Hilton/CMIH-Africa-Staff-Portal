<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchandiser_outlet_user')) {
            Schema::create('merchandiser_outlet_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'outlet_id'], 'merch_outlet_user_unique');
                $table->index(['outlet_id', 'user_id']);
            });
        }

        Schema::table('outlets', function (Blueprint $table) {
            if (! Schema::hasColumn('outlets', 'coordinates_locked_at')) {
                $table->timestamp('coordinates_locked_at')->nullable()->after('longitude');
            }

            if (! Schema::hasColumn('outlets', 'coordinates_captured_by')) {
                $table->foreignId('coordinates_captured_by')->nullable()->after('coordinates_locked_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('outlets', 'coordinates_source')) {
                $table->string('coordinates_source', 32)->nullable()->after('coordinates_captured_by');
            }
        });

        DB::table('outlets')
            ->whereNotNull('registered_by')
            ->orderBy('id')
            ->chunkById(200, function ($outlets) {
                foreach ($outlets as $outlet) {
                    DB::table('merchandiser_outlet_user')->updateOrInsert(
                        [
                            'user_id' => $outlet->registered_by,
                            'outlet_id' => $outlet->id,
                        ],
                        [
                            'assigned_by' => $outlet->registered_by,
                            'assigned_at' => $outlet->created_at,
                            'created_at' => $outlet->created_at,
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            if (Schema::hasColumn('outlets', 'coordinates_captured_by')) {
                $table->dropConstrainedForeignId('coordinates_captured_by');
            }

            foreach (['coordinates_source', 'coordinates_locked_at'] as $column) {
                if (Schema::hasColumn('outlets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('merchandiser_outlet_user');
    }
};
