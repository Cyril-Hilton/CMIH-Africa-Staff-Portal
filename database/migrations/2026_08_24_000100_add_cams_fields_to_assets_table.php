<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'asset_tag')) {
                $table->string('asset_tag')->nullable()->after('brand')->index();
            }

            if (! Schema::hasColumn('assets', 'serial_number')) {
                $table->string('serial_number')->nullable()->after('asset_tag');
            }

            if (! Schema::hasColumn('assets', 'category')) {
                $table->string('category')->nullable()->after('serial_number')->index();
            }

            if (! Schema::hasColumn('assets', 'asset_value')) {
                $table->decimal('asset_value', 14, 2)->nullable()->after('category');
            }

            if (! Schema::hasColumn('assets', 'po_quantity')) {
                $table->unsignedInteger('po_quantity')->default(0)->after('asset_value');
            }

            if (! Schema::hasColumn('assets', 'quantity_procured')) {
                $table->unsignedInteger('quantity_procured')->default(0)->after('po_quantity');
            }

            if (! Schema::hasColumn('assets', 'owner')) {
                $table->string('owner')->nullable()->after('quantity_procured')->index();
            }

            if (! Schema::hasColumn('assets', 'asset_use_type')) {
                $table->string('asset_use_type')->nullable()->after('owner');
            }

            if (! Schema::hasColumn('assets', 'remodel_status')) {
                $table->string('remodel_status')->nullable()->after('asset_use_type')->index();
            }

            if (! Schema::hasColumn('assets', 'remodel_notes')) {
                $table->text('remodel_notes')->nullable()->after('remodel_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('assets', 'remodel_notes') ? 'remodel_notes' : null,
                Schema::hasColumn('assets', 'remodel_status') ? 'remodel_status' : null,
                Schema::hasColumn('assets', 'asset_use_type') ? 'asset_use_type' : null,
                Schema::hasColumn('assets', 'owner') ? 'owner' : null,
                Schema::hasColumn('assets', 'quantity_procured') ? 'quantity_procured' : null,
                Schema::hasColumn('assets', 'po_quantity') ? 'po_quantity' : null,
                Schema::hasColumn('assets', 'asset_value') ? 'asset_value' : null,
                Schema::hasColumn('assets', 'category') ? 'category' : null,
                Schema::hasColumn('assets', 'serial_number') ? 'serial_number' : null,
                Schema::hasColumn('assets', 'asset_tag') ? 'asset_tag' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
