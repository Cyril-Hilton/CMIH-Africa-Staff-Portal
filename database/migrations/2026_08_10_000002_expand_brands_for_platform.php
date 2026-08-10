<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('brands', 'category')) {
                $table->string('category')->nullable()->after('slug');
            }

            if (! Schema::hasColumn('brands', 'headline')) {
                $table->string('headline')->nullable()->after('category');
            }

            if (! Schema::hasColumn('brands', 'description')) {
                $table->text('description')->nullable()->after('headline');
            }

            if (! Schema::hasColumn('brands', 'activation_name')) {
                $table->string('activation_name')->nullable()->after('description');
            }

            if (! Schema::hasColumn('brands', 'activation_type')) {
                $table->string('activation_type')->nullable()->after('activation_name');
            }

            if (! Schema::hasColumn('brands', 'activation_description')) {
                $table->text('activation_description')->nullable()->after('activation_type');
            }

            if (! Schema::hasColumn('brands', 'primary_color')) {
                $table->string('primary_color', 24)->nullable()->after('activation_description');
            }

            if (! Schema::hasColumn('brands', 'secondary_color')) {
                $table->string('secondary_color', 24)->nullable()->after('primary_color');
            }

            if (! Schema::hasColumn('brands', 'accent_color')) {
                $table->string('accent_color', 24)->nullable()->after('secondary_color');
            }

            if (! Schema::hasColumn('brands', 'platform_status')) {
                $table->string('platform_status')->default('active')->after('accent_color')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $columns = [
                'slug',
                'category',
                'headline',
                'description',
                'activation_name',
                'activation_type',
                'activation_description',
                'primary_color',
                'secondary_color',
                'accent_color',
                'platform_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('brands', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
