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
            if (! Schema::hasColumn('users', 'merchandiser_working_days')) {
                $table->json('merchandiser_working_days')->nullable()->after('rsm_id');
            }

            if (! Schema::hasColumn('users', 'merchandiser_daily_outlet_target')) {
                $table->unsignedSmallInteger('merchandiser_daily_outlet_target')->default(8)->after('merchandiser_working_days');
            }

            if (! Schema::hasColumn('users', 'merchandiser_outlet_frequency')) {
                $table->string('merchandiser_outlet_frequency', 32)->default('weekly')->after('merchandiser_daily_outlet_target');
            }
        });

        Schema::table('merchandiser_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandiser_attendances', 'client_recorded_at')) {
                $table->dateTime('client_recorded_at')->nullable()->after('clock_in_time');
            }

            if (! Schema::hasColumn('merchandiser_attendances', 'sync_token')) {
                $table->string('sync_token', 80)->nullable()->unique()->after('client_recorded_at');
            }

            if (! Schema::hasColumn('merchandiser_attendances', 'sync_source')) {
                $table->string('sync_source', 24)->default('live')->after('sync_token');
            }

            if (! Schema::hasColumn('merchandiser_attendances', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('sync_source');
            }
        });

        Schema::table('merchandiser_pcm_clockins', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandiser_pcm_clockins', 'client_recorded_at')) {
                $table->dateTime('client_recorded_at')->nullable()->after('clocked_in_at');
            }

            if (! Schema::hasColumn('merchandiser_pcm_clockins', 'sync_token')) {
                $table->string('sync_token', 80)->nullable()->unique()->after('client_recorded_at');
            }

            if (! Schema::hasColumn('merchandiser_pcm_clockins', 'sync_source')) {
                $table->string('sync_source', 24)->default('live')->after('sync_token');
            }

            if (! Schema::hasColumn('merchandiser_pcm_clockins', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('sync_source');
            }
        });

        Schema::create('merchandiser_outlet_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('merchandiser_visits')->nullOnDelete();
            $table->date('assigned_date');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('status', 24)->default('planned');
            $table->string('source', 24)->default('auto');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'outlet_id', 'assigned_date'], 'merch_assignment_unique_day');
            $table->index(['user_id', 'assigned_date']);
            $table->index(['outlet_id', 'assigned_date']);
        });

        Schema::create('merchandiser_google_form_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('google_form_url', 1000)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('kd_id')->nullable()->constrained('key_distributors')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('category')->nullable();
            $table->string('channel_type', 16)->nullable();
            $table->boolean('google_enabled')->default(true);
            $table->boolean('native_enabled')->default(false);
            $table->string('native_template_key')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_on', 'ends_on']);
            $table->index(['native_enabled', 'native_template_key']);
        });

        Schema::create('merchandiser_google_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_assignment_id')->constrained('merchandiser_google_form_assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('response_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'outlet_id']);
        });

        DB::table('merchandiser_google_form_assignments')->insert([
            'title' => 'Perfect Store Audit',
            'description' => 'Complete the Perfect Store audit using either the live Google Form or the native inbuilt portal form.',
            'google_form_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSfAKE-pKp82legHbJ5qza-R0lTVZ6fagvzG669Lc3PPDaHS6Q/viewform',
            'category' => 'Perfect Store',
            'google_enabled' => true,
            'native_enabled' => true,
            'native_template_key' => 'perfect_store_v1',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('merchandiser_native_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_assignment_id')->constrained('merchandiser_google_form_assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('template_key', 80);
            $table->json('answers');
            $table->json('normalized_metrics')->nullable();
            $table->string('source_google_form_url', 1000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'outlet_id']);
            $table->index(['template_key', 'submitted_at']);
        });

        Schema::create('merchandiser_planograms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('channel_type', 16)->nullable();
            $table->string('reference_file_path')->nullable();
            $table->text('playbook_notes')->nullable();
            $table->json('checklist')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'channel_type']);
        });

        Schema::table('merchandiser_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('merchandiser_visits', 'route_assignment_id')) {
                $table->foreignId('route_assignment_id')->nullable()->after('outlet_id')->constrained('merchandiser_outlet_assignments')->nullOnDelete();
            }

            if (! Schema::hasColumn('merchandiser_visits', 'planogram_id')) {
                $table->foreignId('planogram_id')->nullable()->after('hangers_available')->constrained('merchandiser_planograms')->nullOnDelete();
            }

            if (! Schema::hasColumn('merchandiser_visits', 'planogram_score')) {
                $table->unsignedTinyInteger('planogram_score')->nullable()->after('planogram_id');
            }

            if (! Schema::hasColumn('merchandiser_visits', 'planogram_notes')) {
                $table->text('planogram_notes')->nullable()->after('planogram_score');
            }

            if (! Schema::hasColumn('merchandiser_visits', 'planogram_photo_path')) {
                $table->string('planogram_photo_path')->nullable()->after('planogram_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchandiser_visits', function (Blueprint $table) {
            if (Schema::hasColumn('merchandiser_visits', 'route_assignment_id')) {
                $table->dropConstrainedForeignId('route_assignment_id');
            }

            if (Schema::hasColumn('merchandiser_visits', 'planogram_id')) {
                $table->dropConstrainedForeignId('planogram_id');
            }

            foreach (['planogram_score', 'planogram_notes', 'planogram_photo_path'] as $column) {
                if (Schema::hasColumn('merchandiser_visits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('merchandiser_planograms');
        Schema::dropIfExists('merchandiser_native_form_submissions');
        Schema::dropIfExists('merchandiser_google_form_submissions');
        Schema::dropIfExists('merchandiser_google_form_assignments');
        Schema::dropIfExists('merchandiser_outlet_assignments');

        Schema::table('merchandiser_pcm_clockins', function (Blueprint $table) {
            foreach (['client_recorded_at', 'sync_token', 'sync_source', 'synced_at'] as $column) {
                if (Schema::hasColumn('merchandiser_pcm_clockins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('merchandiser_attendances', function (Blueprint $table) {
            foreach (['client_recorded_at', 'sync_token', 'sync_source', 'synced_at'] as $column) {
                if (Schema::hasColumn('merchandiser_attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['merchandiser_working_days', 'merchandiser_daily_outlet_target', 'merchandiser_outlet_frequency'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
