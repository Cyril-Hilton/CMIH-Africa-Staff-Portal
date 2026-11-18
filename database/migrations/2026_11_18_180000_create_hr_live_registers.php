<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('hr_register_records',function(Blueprint $t){
   $t->id();$t->string('kind',40);$t->string('label');$t->json('data');$t->json('attachment')->nullable();$t->unsignedInteger('revision')->default(1);
   $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
   $t->string('source_key')->nullable()->unique();$t->timestamps();$t->softDeletes();$t->index(['kind','deleted_at']);
  });
  Schema::table('hr_dashboard_activities',function(Blueprint $t){
   $t->json('supporting_staff_ids')->nullable();$t->string('priority',10)->default('Medium');$t->string('linked_type',40)->nullable();$t->unsignedBigInteger('linked_id')->nullable();
   $t->timestamp('completed_at')->nullable();$t->timestamp('approved_at')->nullable();$t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();$t->softDeletes();
  });
  foreach(\Illuminate\Support\Facades\DB::table('hr_dashboard_activities')->where('status','Overdue')->get() as $row){
   $before=(array)$row;$after=$before;$after['status']='In Progress';
   \Illuminate\Support\Facades\DB::table('hr_dashboard_activities')->where('id',$row->id)->update(['status'=>'In Progress']);
   \Illuminate\Support\Facades\DB::table('performance_events')->insert(['entity_type'=>'hr_activity','entity_id'=>$row->id,'actor_id'=>null,'action'=>'normalize_status','before'=>json_encode($before),'after'=>json_encode($after),'reason'=>'Manual Overdue status replaced; overdue is derived automatically from deadline.','created_at'=>now()]);
  }
  Schema::table('users',function(Blueprint $t){$t->string('employment_type',32)->nullable();});
  Schema::table('third_party_vendors',function(Blueprint $t){$t->json('hr_details')->nullable();});
  Schema::table('assets',function(Blueprint $t){$t->json('hr_details')->nullable();});
 }
 public function down(): void {
  Schema::table('third_party_vendors',fn(Blueprint $t)=>$t->dropColumn('hr_details'));
  Schema::table('assets',fn(Blueprint $t)=>$t->dropColumn('hr_details'));
  Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('employment_type'));
  Schema::table('hr_dashboard_activities',function(Blueprint $t){$t->dropConstrainedForeignId('approved_by');$t->dropColumn(['supporting_staff_ids','priority','linked_type','linked_id','completed_at','approved_at','deleted_at']);});
  Schema::dropIfExists('hr_register_records');
 }
};
