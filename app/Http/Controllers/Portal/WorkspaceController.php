<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CollaborativeDocument;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    /**
     * Display the workspace dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // My Workspace documents
        $myDocuments = CollaborativeDocument::where('created_by', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        // Shared with me (excluding ones I created)
        $sharedDocuments = CollaborativeDocument::where('created_by', '!=', $user->id)
            ->whereHas('collaborators', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['collaborators' => function($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->orderByDesc('updated_at')
            ->get();

        // Workflow Action Queue (pending review by me)
        $actionQueue = CollaborativeDocument::where('current_holder_id', $user->id)
            ->where('status', 'under_review')
            ->orderByDesc('updated_at')
            ->get();

        return view('portal.workspace.index', compact('myDocuments', 'sharedDocuments', 'actionQueue'));
    }

    /**
     * Show the document creation form.
     */
    public function create()
    {
        $users = User::internalStaff()
            ->where('status', 'active')
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();

        return view('portal.workspace.create', compact('users'));
    }

    /**
     * Store a newly created workspace document.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:xlsx,xls,csv,doc,docx,ppt,pptx,pdf', 'max:10240'],
        ]);

        $filePath = null;
        $fileName = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('workspace', 'local');
            $fileName = $file->getClientOriginalName();
        }

        $document = CollaborativeDocument::create([
            'title' => $request->title,
            'doc_type' => 'document',
            'content' => $request->content,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'created_by' => Auth::id(),
            'current_holder_id' => Auth::id(),
            'status' => 'draft',
        ]);

        // Handle initial collaborators (default to view)
        if ($request->has('collaborators')) {
            $collabs = [];
            foreach ($request->collaborators as $userId) {
                $collabs[$userId] = ['permission' => 'view'];
            }
            $document->collaborators()->sync($collabs);
        }

        return redirect()->route('portal.workspace.show', $document)
            ->with('status', 'Workspace document successfully created.');
    }

    /**
     * Display a specific workspace document.
     */
    public function show(CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        // Check permission: owner or collaborator
        $isCollaborator = $workspace->collaborators()->where('user_id', $user->id)->exists();
        if ($workspace->created_by !== $user->id && !$isCollaborator && $workspace->current_holder_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403, '🔒 Access Denied. You are not authorized to view this document.');
        }

        // Fetch user's permission level
        $collabRecord = $workspace->collaborators()->where('user_id', $user->id)->first();
        $permission = $collabRecord ? $collabRecord->pivot->permission : (($workspace->created_by === $user->id || $user->hasRole('super_admin')) ? 'edit' : 'view');

        // Fetch eligible recipients for submissions
        $allUsers = User::internalStaff()->where('status', 'active')->where('id', '!=', $user->id)->orderBy('name')->get();
        $lineManagers = $allUsers
            ->filter(fn (User $candidate) => $candidate->isLineManager())
            ->values();
        $coworkerRecipients = $allUsers
            ->reject(fn (User $candidate) => $candidate->isLineManager())
            ->values();
        $lineManager = $user->lineManager;

        return view('portal.workspace.show', compact('workspace', 'permission', 'allUsers', 'lineManager', 'lineManagers', 'coworkerRecipients'));
    }

    /**
     * Show the edit form.
     */
    public function edit(CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        // Guard: owner or editor collaborator
        $isEditor = $workspace->collaborators()->where('user_id', $user->id)->where('permission', 'edit')->exists();
        if ($workspace->created_by !== $user->id && !$isEditor && !$user->hasRole('super_admin')) {
            abort(403, '🔒 Access Denied. You only have view permissions for this document.');
        }

        $users = User::internalStaff()
            ->where('status', 'active')
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();

        return view('portal.workspace.edit', compact('workspace', 'users'));
    }

    /**
     * Update a workspace document.
     */
    public function update(Request $request, CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        // Guard: owner or editor collaborator
        $isEditor = $workspace->collaborators()->where('user_id', $user->id)->where('permission', 'edit')->exists();
        if ($workspace->created_by !== $user->id && !$isEditor && !$user->hasRole('super_admin')) {
            abort(403, '🔒 Access Denied. You only have view permissions for this document.');
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:xlsx,xls,csv,doc,docx,ppt,pptx,pdf', 'max:10240'],
        ]);

        $updates = [
            'title' => $request->title,
            'content' => $request->content,
        ];

        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($workspace->file_path) {
                Storage::disk('local')->delete($workspace->file_path);
                Storage::disk('public')->delete($workspace->file_path);
            }
            $file = $request->file('file');
            $updates['file_path'] = $file->store('workspace', 'local');
            $updates['file_name'] = $file->getClientOriginalName();
        }

        $workspace->update($updates);

        return redirect()->route('portal.workspace.show', $workspace)
            ->with('status', 'Workspace document successfully updated.');
    }

    /**
     * Delete a workspace document.
     */
    public function destroy(CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        if ($workspace->created_by !== $user->id && !$user->hasRole('super_admin')) {
            abort(403, '🔒 Access Denied. Only the creator can delete this document.');
        }

        if ($workspace->file_path) {
            Storage::disk('local')->delete($workspace->file_path);
            Storage::disk('public')->delete($workspace->file_path);
        }

        $workspace->delete();

        return redirect()->route('portal.workspace.index')
            ->with('status', 'Workspace document successfully deleted.');
    }

    /**
     * Manage document collaborators.
     */
    public function updateCollaborators(Request $request, CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        // Only the owner can manage collaborators
        if ($workspace->created_by !== $user->id && !$user->hasRole('super_admin')) {
            abort(403, '🔒 Access Denied. Only the document owner can manage collaborators.');
        }

        $validated = $request->validate([
            'collabs' => ['nullable', 'array'],
            'collabs.*.id' => ['required', 'exists:users,id'],
            'collabs.*.permission' => ['required', 'string', 'in:view,edit'],
        ]);

        $syncData = [];
        if ($request->has('collabs')) {
            foreach ($request->collabs as $collab) {
                $syncData[$collab['id']] = ['permission' => $collab['permission']];
            }
        }

        $workspace->collaborators()->sync($syncData);

        return back()->with('status', 'Collaborators updated successfully.');
    }

    /**
     * Submit document for routing review.
     */
    public function submit(Request $request, CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        if ($workspace->created_by !== $user->id && $workspace->current_holder_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403, '🔒 Access Denied. Only the owner or current holder can route this document.');
        }

        $request->validate([
            'route_target' => ['required', 'string', 'in:manager,user'],
            'recipient_id' => ['nullable', 'exists:users,id'],
        ]);

        $target = $request->route_target;
        $recipientId = $request->recipient_id ? (int) $request->recipient_id : null;

        if ($target === 'manager') {
            $recipientId = $recipientId ?: $workspace->creator->line_manager_id;
            if (!$recipientId) {
                return back()->withErrors(['recipient_id' => 'Please select a line manager to route this document to.'])->withInput();
            }

            $recipient = User::internalStaff()
                ->where('status', 'active')
                ->find($recipientId);

            if (! $recipient || ! $recipient->isLineManager()) {
                return back()->withErrors(['recipient_id' => 'Please select a valid active line manager.'])->withInput();
            }
        } else {
            if (!$recipientId) {
                return back()->withErrors(['recipient_id' => 'Please select a coworker to route to.'])->withInput();
            }

            $recipient = User::internalStaff()
                ->where('status', 'active')
                ->find($recipientId);

            if (! $recipient) {
                return back()->withErrors(['recipient_id' => 'Please select a valid active coworker.'])->withInput();
            }
        }

        $workspace->update([
            'current_holder_id' => $recipientId,
            'status' => 'under_review',
        ]);

        NotificationService::sendApprovalNeededToMany(
            [(int) $recipientId],
            'Workspace Document Review Needed',
            "{$user->name} routed '{$workspace->title}' to you for review.",
            route('portal.workspace.show', $workspace),
            $user->id
        );

        return redirect()->route('portal.workspace.show', $workspace)
            ->with('status', 'Workspace document successfully routed for review.');
    }

    /**
     * Perform workflow actions (Approve / Reject).
     */
    public function action(Request $request, CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        if ($workspace->current_holder_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403, '🔒 Access Denied. Only the current holder can perform workflow actions.');
        }

        $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
        ]);

        $action = $request->action;

        if ($action === 'approve') {
            $workspace->update([
                'status' => 'finalized',
                'current_holder_id' => $workspace->created_by, // Return back to creator
            ]);
            return redirect()->route('portal.workspace.show', $workspace)
                ->with('status', 'Document approved and marked as finalized.');
        } else {
            // Reject Stage
            $workspace->update([
                'status' => 'draft',
                'current_holder_id' => $workspace->created_by,
            ]);

            return redirect()->route('portal.workspace.show', $workspace)
                ->with('status', 'Document rejected and sent back to creator as draft.');
        }
    }

    /**
     * Export workspace document content.
     */
    public function export(CollaborativeDocument $workspace)
    {
        $user = Auth::user();

        // Check view permission
        $isCollaborator = $workspace->collaborators()->where('user_id', $user->id)->exists();
        if ($workspace->created_by !== $user->id && !$isCollaborator && $workspace->current_holder_id !== $user->id && !$user->hasRole('super_admin')) {
            abort(403);
        }

        // If file exists, return file download
        if ($workspace->file_path && Storage::disk('local')->exists($workspace->file_path)) {
            return Storage::disk('local')->download($workspace->file_path, $workspace->file_name);
        }

        if ($workspace->file_path && Storage::disk('public')->exists($workspace->file_path)) {
            return Storage::disk('public')->download($workspace->file_path, $workspace->file_name);
        }

        // Otherwise, export CKEditor text content as an HTML file
        $content = "
        <html>
        <head>
            <title>{$workspace->title}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 30px; line-height: 1.6; }
                h1 { border-bottom: 2px solid #333; padding-bottom: 10px; color: #222; }
                table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                table, th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <h1>{$workspace->title}</h1>
            <p><strong>Status:</strong> " . strtoupper($workspace->status) . "</p>
            <p><strong>Creator:</strong> {$workspace->creator->name}</p>
            <hr/>
            <div>{$workspace->content}</div>
        </body>
        </html>";

        $fileName = str_replace(' ', '_', $workspace->title) . '_export.html';
        return response($content, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
