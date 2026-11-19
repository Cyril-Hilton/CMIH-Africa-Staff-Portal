<?php
namespace App\Services\Creative;

use App\Models\{CreativeRequest, CrmPipelineProject, User};
use App\Services\{PerformanceWorkflow, ProjectJourney};
use Illuminate\Support\Facades\DB;

class CreativeProjectConnection
{
    public function update(CreativeRequest $request, User $actor, int $revision, ?int $projectId, ?string $deadline=null): void
    {
        DB::transaction(function () use ($request, $actor, $revision, $projectId, $deadline) {
            $brief = CreativeRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless(CreativeAccess::view($actor, $brief), 404);
            abort_unless(CreativeAccess::lead($actor) || ($brief->requester_id === $actor->id && User::normalizeDepartmentKey($actor->department) === 'client_relations'), 403);
            abort_if($brief->parent_request_id, 422, 'Connect the parent brief; its work packages inherit that project.');
            abort_if($brief->revision !== $revision, 409, 'This brief changed. Refresh before connecting its project.');
            $journey = app(ProjectJourney::class);
            foreach (array_unique(array_filter([$brief->crm_project_id, $projectId])) as $id) {
                $project = CrmPipelineProject::where('source_period_confirmed', true)->findOrFail($id);
                abort_unless($journey->canLink($actor, $project), 403);
            }
            abort_if($deadline&&!$projectId,422,'Connect a Client Relations project before recording its deadline.');
            $changed=(int)$brief->crm_project_id!==(int)$projectId;
            if (!$changed&&!$deadline) return;
            $deadlineBefore=$deadline&&!$changed?app(CreativeTimeline::class)->build($brief)['client_relations_deadline']:null;
            $before = $brief->only(['crm_project_id', 'revision']);
            $brief->update(['crm_project_id' => $projectId, 'revision' => $brief->revision + 1]);
            if($changed)PerformanceWorkflow::record('creative_request', $brief->id, $actor->id, $projectId ? 'project_linked' : 'project_unlinked', $before,
                $brief->only(['crm_project_id', 'revision']), 'Updated the Client Relations project connection. Original brief, assignment and milestone dates retained.');
            if($deadline)PerformanceWorkflow::record('creative_request',$brief->id,$actor->id,'client_relations_deadline_recorded',['client_relations_deadline'=>$deadlineBefore],['crm_project_id'=>$projectId,'client_relations_deadline'=>\Illuminate\Support\Carbon::parse($deadline)->toIso8601String(),'contact_id'=>$project->owner_id],'Recorded the reported Client Relations deadline. The recorder is identified separately from the account owner.');
            $brief->unsetRelation('crmProject');
            app(CreativeWorkflow::class)->notify($brief,[$actor->id,...app(CreativeWorkflow::class)->leads()],'Project connection & timeline updated',$brief->title.' has an updated project connection or reported Client Relations deadline. Open its accountability timeline.');
        });
    }
}
