<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Update;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UpdateController extends Controller
{
    public function index(): View
    {
        $updates = Update::with('user')->latest()->paginate(10);

        return view('admin.updates', compact('updates'));
    }

    public function edit(Update $update): View
    {
        $staff = User::internalStaff()->orderBy('name')->get();

        return view('admin.updates-edit', compact('update', 'staff'));
    }

    public function update(Request $request, Update $update): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'string', Rule::in(['on_track', 'at_risk', 'delayed', 'completed'])],
            'priority' => ['required', 'string', Rule::in(['high', 'medium', 'low'])],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'timeline' => ['nullable', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === 'completed' && (int) $validated['progress'] !== 100) {
            $validated['progress'] = 100;
        }

        $update->update($validated);

        return redirect()->route('admin.updates')->with('status', 'Update revised.');
    }

    public function destroy(Update $update): RedirectResponse
    {
        $update->delete();

        return back()->with('status', 'Update deleted.');
    }
}
