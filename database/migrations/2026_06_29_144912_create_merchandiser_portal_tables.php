<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Regions
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('timezone')->default('Africa/Accra'); // e.g. Africa/Accra, Africa/Lagos
            $table->timestamps();
        });

        // 2. Key Distributors
        Schema::create('key_distributors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });

        // 3. Outlets (Stores)
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('kd_id')->constrained('key_distributors')->cascadeOnDelete();
            $table->string('channel_type')->default('GT'); // SSM or GT
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });

        // 4. SKUs
        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 5. Merchandiser Attendances (3 times daily clock-in)
        Schema::create('merchandiser_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->string('clock_in_type', 16); // morning, midday, cob
            $table->dateTime('clock_in_time');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('distance_from_outlet', 8, 2); // distance in meters
            $table->string('status', 16)->default('on-time'); // on-time, late, absent
            $table->timestamps();
        });

        // 6. Merchandiser Locations (Real-Time Background Pings)
        Schema::create('merchandiser_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->dateTime('recorded_at');
            $table->timestamps();
        });

        // 7. Merchandiser Visits (Store execution data reports)
        Schema::create('merchandiser_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->boolean('branded_shelf_available')->default(false);
            $table->boolean('hangers_available')->default(false);
            $table->timestamps();
        });

        // 8. Merchandiser Visit SKUs (Dynamic SKU performance details)
        Schema::create('merchandiser_visit_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('merchandiser_visits')->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained('skus')->cascadeOnDelete();
            $table->integer('osa_quantity')->default(0);
            $table->boolean('npd_present')->default(false);
            $table->integer('facing')->default(0);
            $table->decimal('share_of_shelf', 5, 2)->default(0.00); // percentage value (e.g. 25.50%)
            $table->boolean('planogram_compliant')->default(false);
            $table->timestamps();
        });

        // 9. Merchandiser Orders
        Schema::create('merchandiser_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('kd_id')->constrained('key_distributors')->cascadeOnDelete();
            $table->string('status', 16)->default('pending'); // pending, sent, fulfilled
            $table->timestamps();
        });

        // 10. Merchandiser Order Items
        Schema::create('merchandiser_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('merchandiser_orders')->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained('skus')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });

        // 11. Add merchandiser pairing relationship columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('line_manager_id')->constrained('users')->nullOnDelete();
            $table->foreignId('kd_id')->nullable()->after('supervisor_id')->constrained('key_distributors')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('kd_id')->constrained('regions')->nullOnDelete();
            $table->foreignId('tm_id')->nullable()->after('region_id')->constrained('users')->nullOnDelete();
            $table->foreignId('dsr_id')->nullable()->after('tm_id')->constrained('users')->nullOnDelete();
            $table->foreignId('rsm_id')->nullable()->after('dsr_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropForeign(['kd_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['tm_id']);
            $table->dropForeign(['dsr_id']);
            $table->dropForeign(['rsm_id']);
            $table->dropColumn(['supervisor_id', 'kd_id', 'region_id', 'tm_id', 'dsr_id', 'rsm_id']);
        });

        Schema::dropIfExists('merchandiser_order_items');
        Schema::dropIfExists('merchandiser_orders');
        Schema::dropIfExists('merchandiser_visit_skus');
        Schema::dropIfExists('merchandiser_visits');
        Schema::dropIfExists('merchandiser_locations');
        Schema::dropIfExists('merchandiser_attendances');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('key_distributors');
        Schema::dropIfExists('regions');
    }
};
