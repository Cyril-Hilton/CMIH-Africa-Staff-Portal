<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand-ash">Task details · #{{ $task->id }}</p>
                <h2 class="mt-2 text-3xl font-display text-brand-white">{{ $task->title ?: $task->creativeRequest?->title ?: 'Untitled task' }}</h2>
            </div>
            <a class="rounded-full border border-brand-white/20 px-4 py-2 underline" href="{{ \App\Http\Controllers\Portal\TaskController::taskReturnUrl(request()) }}">{{ str_starts_with(\App\Http\Controllers\Portal\TaskController::taskReturnUrl(request()), '/dashboard') ? 'Back to activity tracker' : 'Back to tasks' }}</a>
        </div>
    </x-slot>
    <article class="glass-panel max-w-6xl space-y-6 rounded-2xl border border-brand-white/10 p-6">
        <dl class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                'Assigned to' => $task->assignee?->name ?? 'Unassigned',
                'Supporting staff' => $task->supportingStaff->pluck('name')->implode(', ') ?: 'None recorded',
                'Assigned by' => $task->assigner?->name ?? 'Not recorded',
                'Department' => \App\Models\User::departmentLabel($task->department ?: $task->assignee?->department),
                'Client / brand' => $task->client_name ?: $task->creativeRequest?->client ?: 'Not recorded',
                'Status' => match ($task->completion_review_status) { 'pending' => 'Awaiting review', 'reverted' => 'Returned for correction', default => $task->status ?: 'Not recorded' },
                'Priority' => $task->priority ?: 'Not recorded',
                'Entered' => $task->created_at?->format('d M Y, H:i') ?? 'Not recorded',
                'Due' => $task->due_on?->format('d M Y') ?? 'Not recorded',
            ] as $label => $value)
                <div><dt class="text-sm text-brand-ash">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <section class="border-t border-brand-white/10 pt-6">
            <h3 class="mb-3 text-xl font-semibold">Brief / task details</h3>
            <div class="report-rich-text whitespace-pre-wrap">{!! \App\Support\ReportRichText::html($task->details ?: $task->creativeRequest?->brief ?: 'No additional details recorded.') !!}</div>
        </section>
        @if($task->notes_feedback)
            <section><h3 class="mb-3 text-xl font-semibold">Updates / feedback</h3><div class="report-rich-text whitespace-pre-wrap">{!! \App\Support\ReportRichText::html($task->notes_feedback) !!}</div></section>
        @endif
        @if($task->creativeRequest)
            <a class="inline-block rounded-full border border-brand-white/20 px-4 py-2 underline" href="{{ route('portal.creative', ['request' => $task->creativeRequest->id]) }}">Open creative brief and updates</a>
        @elseif($task->canBeEditedBy(auth()->user()))
            <a class="inline-block rounded-full border border-brand-white/20 px-4 py-2 underline" href="{{ route('portal.tasks.edit', ['task' => $task->id, 'return_to' => request('return_to')]) }}">Edit task</a>
        @endif
    </article>
</x-app-layout>
