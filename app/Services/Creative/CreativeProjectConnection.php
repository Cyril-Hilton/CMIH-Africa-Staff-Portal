<?php
namespace App\Services\Creative;

use App\Models\{CreativeRequest, CrmPipelineProject, User};
use App\Services\{PerformanceWorkflow, ProjectJourney};
use Illuminate\Support\Facades\DB;

class CreativeProjectConnection
{
    public function update(CreativeRequest $request, User $actor, int $revision, ?int $projectId): void
    {
        DB::transaction(function () use ($request, $actor, $revision, $projectId) {
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
            if ((int) $brief->crm_project_id === (int) $projectId) return;
            $before = $brief->only(['crm_project_id', 'revision']);
            $brief->update(['crm_project_id' => $projectId, 'revision' => $brief->revision + 1]);
            PerformanceWorkflow::record('creative_request', $brief->id, $actor->id, $projectId ? 'project_linked' : 'project_unlinked', $before,
                $brief->only(['crm_project_id', 'revision']), 'Updated the Client Relations project connection. Original brief, assignment and milestone dates retained.');
        });
    }
}
