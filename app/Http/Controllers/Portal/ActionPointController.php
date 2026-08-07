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
    public function store(Request $request): RedirectResponse
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

        return back()->with('status', 'Meeting Action Point added successfully.');
    }

    public function update(Request $request, ActionPoint $actionPoint): RedirectResponse
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

        $actionPoint->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Action Point updated successfully.');
    }

    public function destroy(Request $request, ActionPoint $actionPoint): RedirectResponse
    {
        $actionPoint->delete();

        return back()->with('status', 'Action Point deleted successfully.');
    }
}
