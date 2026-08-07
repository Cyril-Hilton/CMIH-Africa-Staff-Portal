<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->string('reference_image_path')->nullable()->after('name');
            $table->json('aliases')->nullable()->after('reference_image_path');
            $table->text('ai_reference_notes')->nullable()->after('aliases');
        });

        Schema::table('merchandiser_visit_skus', function (Blueprint $table) {
            $table->integer('ai_predicted_quantity')->nullable()->after('planogram_compliant');
            $table->integer('ai_predicted_facing')->nullable()->after('ai_predicted_quantity');
            $table->decimal('ai_predicted_share_of_shelf', 5, 2)->nullable()->after('ai_predicted_facing');
            $table->boolean('ai_predicted_planogram_compliant')->nullable()->after('ai_predicted_share_of_shelf');
            $table->decimal('ai_confidence', 5, 2)->nullable()->after('ai_predicted_planogram_compliant');
            $table->json('ai_detection_boxes')->nullable()->after('ai_confidence');
            $table->json('ai_raw_detection')->nullable()->after('ai_detection_boxes');
        });

        Schema::table('merchandiser_visits', function (Blueprint $table) {
            $table->boolean('ai_detection_review_required')->default(false)->after('ai_detection_notes');
            $table->timestamp('ai_detection_completed_at')->nullable()->after('ai_detection_review_required');
        });
    }

    public function down(): void
    {
        Schema::table('merchandiser_visits', function (Blueprint $table) {
            $table->dropColumn([
                'ai_detection_review_required',
                'ai_detection_completed_at',
            ]);
        });

        Schema::table('merchandiser_visit_skus', function (Blueprint $table) {
            $table->dropColumn([
                'ai_predicted_quantity',
                'ai_predicted_facing',
                'ai_predicted_share_of_shelf',
                'ai_predicted_planogram_compliant',
                'ai_confidence',
                'ai_detection_boxes',
                'ai_raw_detection',
            ]);
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn([
                'reference_image_path',
                'aliases',
                'ai_reference_notes',
            ]);
        });
    }
};
