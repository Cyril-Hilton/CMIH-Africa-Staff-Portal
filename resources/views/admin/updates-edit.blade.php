<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Team Updates</p>
                <h2 class="text-3xl font-display text-brand-white">Edit Update</h2>
            </div>
            <a href="{{ route('admin.updates') }}" class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">Back to Updates</a>
        </div>
    </x-slot>

    <div class="glass-panel rounded-2xl p-6 max-w-6xl">
        <form method="POST" action="{{ route('admin.updates.update', $update) }}" class="space-y-4">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="user_id" :value="__('Update Owner')" />
                <select id="user_id" name="user_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected(old('user_id', $update->user_id) == $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="title" :value="__('Title')" />
                <x-text-input id="title" name="title" type="text" required :value="old('title', $update->title)" />
            </div>

            <div>
                <x-input-label for="summary" :value="__('Summary')" />
                <textarea id="summary" name="summary" rows="4" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>{{ old('summary', $update->summary) }}</textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                        @foreach (['on_track' => 'On Track', 'at_risk' => 'At Risk', 'delayed' => 'Delayed', 'completed' => 'Completed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $update->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" :value="__('Priority')" />
                    <select id="priority" name="priority" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                        @foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $update->priority) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="progress" :value="__('Progress (%)')" />
                    <x-text-input id="progress" name="progress" type="number" min="0" max="100" required :value="old('progress', $update->progress)" />
                </div>
                <div>
                    <x-input-label for="timeline" :value="__('Timeline')" />
                    <x-text-input id="timeline" name="timeline" type="text" :value="old('timeline', $update->timeline)" />
                </div>
            </div>

            <div>
                <x-input-label for="due_on" :value="__('Target Date')" />
                <x-text-input id="due_on" name="due_on" type="date" required :value="old('due_on', optional($update->due_on)->format('Y-m-d'))" />
            </div>

            <div>
                <x-input-label for="notes" :value="__('Notes')" />
                <textarea id="notes" name="notes" rows="3" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">{{ old('notes', $update->notes) }}</textarea>
            </div>

            <x-primary-button class="w-full justify-center">Update Progress</x-primary-button>
        </form>
    </div>
</x-app-layout>
