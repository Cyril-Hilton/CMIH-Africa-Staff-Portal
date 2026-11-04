<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('leave_applications',function(Blueprint $t){$t->text('handover_notes')->nullable();$t->json('handover_attachment')->nullable();$t->json('handover_history')->nullable();$t->unsignedInteger('handover_revision')->default(1);$t->timestamp('handover_acknowledged_at')->nullable();$t->foreignId('handover_acknowledged_by')->nullable()->constrained('users');$t->text('handover_reply')->nullable();});}
 public function down():void {Schema::table('leave_applications',function(Blueprint $t){$t->dropConstrainedForeignId('handover_acknowledged_by');$t->dropColumn(['handover_notes','handover_attachment','handover_history','handover_revision','handover_acknowledged_at','handover_reply']);});}
};
