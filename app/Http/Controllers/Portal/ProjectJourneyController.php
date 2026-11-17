<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\{CreativeRequest, CrmPipelineProject};
use App\Services\Creative\CreativeAccess;
use App\Services\{PerformanceWorkflow, ProjectJourney};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectJourneyController extends Controller
{
    public function index(Request $request, ProjectJourney $journey)
    {
        $data = $journey->build($request);
        $view = $request->boolean('fragment') ? 'portal.project-journey.results' : 'portal.project-journey.index';

        return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
    }

    public function link(Request $request, ProjectJourney $journey)
    {
        $actor = $request->user();
        abort_unless($journey->canAccess($actor), 403);
        $data = $request->validate(['project_id' => 'required|integer|min:1', 'brief_id' => 'required|integer|min:1', 'action' => 'nullable|in:link,unlink']);
        $project = CrmPipelineProject::where('source_period_confirmed', true)->findOrFail($data['project_id']);
        abort_unless($journey->canLink($actor, $project), 403);

        $unlink = ($data['action'] ?? 'link') === 'unlink';
        DB::transaction(function () use ($actor, $data, $project, $unlink) {
            $brief = CreativeRequest::visibleTo($actor)->whereNull('parent_request_id')->lockForUpdate()->findOrFail($data['brief_id']);
            abort_unless(CreativeAccess::lead($actor) || (int) $brief->requester_id === $actor->id, 403);
            abort_if($brief->crm_project_id && (int) $brief->crm_project_id !== $project->id, 409, 'This brief is already linked to another project.');
            $nextProjectId = $unlink ? null : $project->id;
            if ($brief->crm_project_id === $nextProjectId) {
                return;
            }

            $before = $brief->only('crm_project_id', 'revision');
            $brief->update(['crm_project_id' => $nextProjectId, 'revision' => $brief->revision + 1]);
            PerformanceWorkflow::record('creative_request', $brief->id, $actor->id, $unlink ? 'project_unlinked' : 'project_linked', $before,
                $brief->only('crm_project_id', 'revision'), $unlink ? 'Corrected the linked Client Relations project.' : 'Linked to the recorded Client Relations project.');
        });

        return redirect()->route('portal.project-journey', ['project' => $project->id])->with('status', $unlink ? 'Creative brief unlinked.' : 'Creative brief linked.');
    }
}
