<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add last_seen_at for online presence tracking
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('remember_token');
        });

        // Read receipts: track who read which message and when
        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->unique(['message_id', 'user_id']);
        });

        // Add reply-to, edited, deleted flags to messages
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete()->after('user_id');
            $table->boolean('is_edited')->default(false)->after('attachment_type');
            $table->boolean('is_deleted')->default(false)->after('is_edited');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn(['reply_to_id', 'is_edited', 'is_deleted']);
        });

        Schema::dropIfExists('message_reads');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
