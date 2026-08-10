<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('brand_activations')) {
            Schema::table('brand_activations', function (Blueprint $table) {
                if (! Schema::hasColumn('brand_activations', 'target_unit')) {
                    $table->string('target_unit')->nullable()->after('target_reach');
                }

                if (! Schema::hasColumn('brand_activations', 'activation_plan')) {
                    $table->json('activation_plan')->nullable()->after('locations');
                }

                if (! Schema::hasColumn('brand_activations', 'banner_path')) {
                    $table->string('banner_path')->nullable()->after('description');
                }

                if (! Schema::hasColumn('brand_activations', 'client_share_expires_at')) {
                    $table->timestamp('client_share_expires_at')->nullable()->index()->after('client_share_token');
                }
            });
        }

        if (Schema::hasTable('brand_consumer_entries')) {
            Schema::table('brand_consumer_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('brand_consumer_entries', 'current_choice')) {
                    $table->string('current_choice')->nullable()->after('result_type');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'purchase_intent')) {
                    $table->string('purchase_intent')->nullable()->after('current_choice');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'preferred_channel')) {
                    $table->string('preferred_channel')->nullable()->after('purchase_intent');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'is_new_to_brand')) {
                    $table->boolean('is_new_to_brand')->nullable()->after('preferred_channel');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'marketing_consent')) {
                    $table->boolean('marketing_consent')->default(false)->after('is_new_to_brand');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'data_consent')) {
                    $table->boolean('data_consent')->default(false)->after('marketing_consent');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'verification_token')) {
                    $table->string('verification_token')->nullable()->unique()->after('data_consent');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'otp_code')) {
                    $table->string('otp_code', 12)->nullable()->after('verification_token');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'otp_verified_at')) {
                    $table->timestamp('otp_verified_at')->nullable()->index()->after('otp_code');
                }

                if (! Schema::hasColumn('brand_consumer_entries', 'reward_code')) {
                    $table->string('reward_code')->nullable()->index()->after('otp_verified_at');
                }
            });
        }

        if (Schema::hasTable('brand_field_activities')) {
            Schema::table('brand_field_activities', function (Blueprint $table) {
                if (! Schema::hasColumn('brand_field_activities', 'status')) {
                    $table->string('status')->default('recorded')->index()->after('activity_type');
                }

                if (! Schema::hasColumn('brand_field_activities', 'conversion_count')) {
                    $table->unsignedInteger('conversion_count')->default(0)->after('units');
                }

                if (! Schema::hasColumn('brand_field_activities', 'transaction_value')) {
                    $table->decimal('transaction_value', 12, 2)->nullable()->after('conversion_count');
                }

                if (! Schema::hasColumn('brand_field_activities', 'reference_code')) {
                    $table->string('reference_code')->nullable()->index()->after('transaction_value');
                }
            });
        }

        if (! Schema::hasTable('brand_publications')) {
            Schema::create('brand_publications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
                $table->foreignId('brand_activation_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->string('category')->nullable()->index();
                $table->string('status')->default('published')->index();
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->string('image_path')->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('brand_activity_logs')) {
            Schema::create('brand_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('brand_activation_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action')->index();
                $table->string('context')->nullable()->index();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['brand_id', 'created_at']);
            });
        }

        if (Schema::hasTable('brand_publications') && Schema::hasTable('brands')) {
            $now = now();
            $brands = \Illuminate\Support\Facades\DB::table('brands')
                ->where('platform_status', 'active')
                ->get(['id', 'name']);

            foreach ($brands as $brand) {
                if (\Illuminate\Support\Facades\DB::table('brand_publications')->where('brand_id', $brand->id)->exists()) {
                    continue;
                }

                $activation = Schema::hasTable('brand_activations')
                    ? \Illuminate\Support\Facades\DB::table('brand_activations')->where('brand_id', $brand->id)->latest('id')->first()
                    : null;

                \Illuminate\Support\Facades\DB::table('brand_publications')->insert([
                    'brand_id' => $brand->id,
                    'brand_activation_id' => $activation?->id,
                    'title' => $brand->name.' Activation Workspace Is Live',
                    'category' => 'Activation Update',
                    'status' => 'published',
                    'summary' => 'This brand now has a connected activation workspace for consumer capture, support staff execution, agency reporting and client views.',
                    'body' => 'CMIH field teams can capture consumer entries, record activities, track performance and prepare client-ready reports from the Brands Platform.',
                    'published_at' => $now,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_activity_logs');
        Schema::dropIfExists('brand_publications');

        if (Schema::hasTable('brand_field_activities')) {
            Schema::table('brand_field_activities', function (Blueprint $table) {
                foreach (['status', 'conversion_count', 'transaction_value', 'reference_code'] as $column) {
                    if (Schema::hasColumn('brand_field_activities', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('brand_consumer_entries')) {
            Schema::table('brand_consumer_entries', function (Blueprint $table) {
                foreach ([
                    'current_choice',
                    'purchase_intent',
                    'preferred_channel',
                    'is_new_to_brand',
                    'marketing_consent',
                    'data_consent',
                    'verification_token',
                    'otp_code',
                    'otp_verified_at',
                    'reward_code',
                ] as $column) {
                    if (Schema::hasColumn('brand_consumer_entries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('brand_activations')) {
            Schema::table('brand_activations', function (Blueprint $table) {
                foreach (['target_unit', 'activation_plan', 'banner_path', 'client_share_expires_at'] as $column) {
                    if (Schema::hasColumn('brand_activations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
