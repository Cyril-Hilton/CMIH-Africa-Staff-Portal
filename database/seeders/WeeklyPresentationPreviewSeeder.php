<?php
namespace Database\Seeders;
use App\Models\User;
use App\Models\WeeklyConsolidatedItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class WeeklyPresentationPreviewSeeder extends Seeder {
 public function run():void {
  if(!app()->environment(['local','testing'])||config('database.default')!=='sqlite'||basename(config('database.connections.sqlite.database'))!=='cmih-upgrade-preview.sqlite') throw new \RuntimeException('Only the isolated local preview may receive these sample records.');
  $examples=[
   'brands_marketing'=>[['Shopper Marketing','Coverage & Availability Review','Review field coverage, on-shelf availability, shelf standards and corrective actions. Coordinate merchandiser and supervisor follow-up.'],['Data Analytics','Perfect Store Dashboard Refresh','Consolidate field submissions, validate reported figures and refresh the Perfect Store and merchandising dashboards.'],['Shopper Marketing','Promoter & Retail Visibility Coordination','Confirm promoter training, outlet allocation and POSM deployment readiness. Record ownership and next actions.']],
   'hr_admin'=>[['Recruitment Coordination','Interview scheduling and candidate communication','Prepare interview schedules, confirm panel availability and send invitations.'],['Staff Development','Training attendance and completion review','Reconcile training attendance, confirm outstanding completions and prepare next-week follow-up.'],['Workplace Administration','Fleet and office maintenance follow-up','Confirm vehicle availability, track repair progress and follow up on office maintenance actions.']],
   'finance'=>[['Month-End Processing','Invoice reconciliation','Check supporting documents, resolve discrepancies and prepare reconciled entries for independent review.'],['Supplier Processing','Payment documentation review','Confirm invoice references and approval coverage. Financial values remain in the protected Finance module.'],['Reporting Controls','Outstanding document follow-up','Follow up on missing supporting documents and update processing status.']],
   'operations_projects'=>[['Activation Readiness','Venue and setup coordination','Confirm venue access, setup requirements, supplier delivery windows and readiness checks.'],['Asset Deployment','POSM deployment and returns','Check asset codes, deployment quantities, condition records and expected return dates.'],['Project Close-Out','Execution report preparation','Collect execution evidence, record outstanding actions and prepare the project report.']],
  ];
  WeeklyConsolidatedItem::where('campaign_name','like','SAMPLE · %')->where('notes','Synthetic local presentation preview. Not real operational evidence.')->update(['notes'=>null]);
  DB::transaction(function()use($examples){foreach($examples as $dept=>$rows){$owner=User::where('email',str_replace('_','-',$dept).'.staff@example.test')->firstOrFail();foreach($rows as $i=>[$client,$project,$text]) WeeklyConsolidatedItem::firstOrCreate(['department'=>$dept,'campaign_name'=>'SAMPLE · '.($dept==='brands_marketing'?$client:$project),'client_name'=>'SAMPLE · '.($dept==='brands_marketing'?$project:$client),'workflow_state'=>'legacy'],['week_start'=>now()->startOfWeek(),'week_end'=>now()->endOfWeek(),'lead_staff_id'=>$owner->id,'created_by'=>$owner->id,'updated_by'=>$owner->id,'supporting_staff_ids'=>[],'deliverables'=>'<p>'.$text.'</p>','status'=>['Planned','In Progress','Done'][$i],'progress_percent'=>[0,55,100][$i],'priority'=>['High','Medium','Low'][$i],'custom_fields'=>['_local_preview'=>true]]);}});
 }
}
