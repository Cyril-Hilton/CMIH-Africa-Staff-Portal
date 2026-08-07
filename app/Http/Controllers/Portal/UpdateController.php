<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Update;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UpdateController extends Controller
{
    public function index(): View
    {
        $updates = Update::with('user')->latest()->paginate(8);

        return view('portal.updates', compact('updates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'string', Rule::in(['on_track', 'at_risk', 'delayed', 'completed'])],
            'priority' => ['required', 'string', 'in:high,medium,low'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'timeline' => ['nullable', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['status'] === 'completed' && (int) $validated['progress'] !== 100) {
            $validated['progress'] = 100;
        }

        $update = Update::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        if ($validated['status'] === 'completed' && (int) $validated['progress'] === 100) {
            $user = $request->user();
            $normalizedTitle = Str::of($validated['title'])->lower()->trim()->toString();

            $task = Task::where('assigned_to', $user->id)
                ->whereRaw('LOWER(title) = ?', [$normalizedTitle])
                ->latest()
                ->first();

            if ($task) {
                $task->update([
                    'status' => 'done',
                    'due_on' => $task->due_on ?: $validated['due_on'],
                ]);
            } else {
                $details = $validated['summary'];

                if (! empty($validated['notes'])) {
                    $details .= "\nNotes: ".$validated['notes'];
                }

                Task::create([
                    'title' => $validated['title'],
                    'details' => $details,
                    'assigned_to' => $user->id,
                    'assigned_by' => $user->id,
                    'department' => $user->department ?? 'operations',
                    'status' => 'done',
                    'priority' => $validated['priority'],
                    'due_on' => $validated['due_on'],
                ]);
            }
        }

        return back()->with('status', 'Update shared with the team.');
    }
}
