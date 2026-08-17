<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'brand')) {
                $table->string('brand')->nullable();
            }

            if (! Schema::hasColumn('assets', 'warehouse_location')) {
                $table->string('warehouse_location')->nullable();
            }

            if (! Schema::hasColumn('assets', 'warehouse_quantity')) {
                $table->unsignedInteger('warehouse_quantity')->default(1);
            }

            if (! Schema::hasColumn('assets', 'is_warehouse_tracked')) {
                $table->boolean('is_warehouse_tracked')->default(false);
            }

            if (! Schema::hasColumn('assets', 'warehouse_notes')) {
                $table->text('warehouse_notes')->nullable();
            }

            if (! Schema::hasColumn('assets', 'last_handled_by')) {
                $table->foreignId('last_handled_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('assets', 'last_handled_at')) {
                $table->timestamp('last_handled_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'last_handled_by')) {
                $table->dropForeign(['last_handled_by']);
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('assets', 'brand') ? 'brand' : null,
                Schema::hasColumn('assets', 'warehouse_location') ? 'warehouse_location' : null,
                Schema::hasColumn('assets', 'warehouse_quantity') ? 'warehouse_quantity' : null,
                Schema::hasColumn('assets', 'is_warehouse_tracked') ? 'is_warehouse_tracked' : null,
                Schema::hasColumn('assets', 'warehouse_notes') ? 'warehouse_notes' : null,
                Schema::hasColumn('assets', 'last_handled_by') ? 'last_handled_by' : null,
                Schema::hasColumn('assets', 'last_handled_at') ? 'last_handled_at' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
