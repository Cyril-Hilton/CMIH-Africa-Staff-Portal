<?php

namespace App\Console\Commands;

use App\Models\{CreativeRequest, Task};
use App\Services\PerformanceWorkflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCreativeHodDeadlines extends Command
{
    protected $signature='creative:sync-hod-deadlines {--apply : Align open linked tasks with the recorded HOD deadline}';
    protected $description='Align open creative task dates with recorded HOD plans, retaining every prior date in the audit trail';

    public function handle(): int
    {
        $changed=0;
        CreativeRequest::whereNotNull('task_id')->whereNotNull('internal_eta')->whereNotNull('planned_by')
            ->whereNotIn('stage',['completed','archived'])->where('is_group',false)->chunkById(100,function($briefs) use(&$changed) {
                foreach($briefs as $candidate) DB::transaction(function() use($candidate,&$changed) {
                    $brief=CreativeRequest::whereKey($candidate->id)->lockForUpdate()->first();
                    $task=Task::whereKey($brief->task_id)->lockForUpdate()->first();
                    if(!$task || $task->completion_review_status==='approved' || (int)$task->assigned_to!==(int)$brief->assignee_id
                        || $task->due_on?->toDateString()===$brief->internal_eta->toDateString())return;
                    $changed++;
                    if(!$this->option('apply'))return;
                    $before=$task->only('due_on');$task->due_on=$brief->internal_eta;$task->save();
                    PerformanceWorkflow::record('task',$task->id,null,'hod_deadline_synchronised',$before,$task->only('due_on')+['creative_request_id'=>$brief->id,'planned_by'=>$brief->planned_by],
                        'Aligned the open task with the Creative HOD delivery deadline. The requester deadline remains separately recorded in the brief.');
                });
            });
        $this->line(json_encode(['mode'=>$this->option('apply')?'applied':'preview','tasks'=>$changed]));
        return self::SUCCESS;
    }
}
