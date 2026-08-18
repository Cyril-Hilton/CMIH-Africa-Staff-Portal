<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ActionPoint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActionPointController extends Controller
{
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'action_point' => ['required', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignee_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'done', 'not_done'])],
            'comments' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (! empty($validated['assignee_id'])) {
            $user = User::find($validated['assignee_id']);
            if ($user) {
                $validated['assignee_name'] = $user->name;
            }
        }

        ActionPoint::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Meeting Action Point added successfully.']);
        }

        return back()->with('status', 'Meeting Action Point added successfully.');
    }

    public function update(Request $request, ActionPoint $actionPoint): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        // Support Quick Status update
        if ($request->has('status') && ! $request->has('action_point')) {
            $validated = $request->validate([
                'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'done', 'not_done'])],
            ]);

            $actionPoint->update([
                'status' => $validated['status'],
                'updated_by' => $request->user()->id,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Action point status updated to ' . $actionPoint->status_label,
                    'status' => $actionPoint->status,
                ]);
            }

            return back()->with('status', 'Action point status updated to ' . $actionPoint->status_label);
        }

        $validated = $request->validate([
            'action_point' => ['required', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignee_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'done', 'not_done'])],
            'comments' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (! empty($validated['assignee_id'])) {
            $user = User::find($validated['assignee_id']);
            if ($user) {
                $validated['assignee_name'] = $user->name;
            }
        }

        $actionPoint->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Action Point updated successfully.']);
        }

        return back()->with('status', 'Action Point updated successfully.');
    }

    public function destroy(Request $request, ActionPoint $actionPoint): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $actionPoint->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Action Point deleted successfully.']);
        }

        return back()->with('status', 'Action Point deleted successfully.');
    }
}
