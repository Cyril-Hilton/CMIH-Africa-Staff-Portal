<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Progress Updates</p>
            <h2 class="text-3xl font-display text-brand-white">Share Progress with the Team</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    @php
        $priorityStyles = [
            'high' => 'border-brand-red/40 bg-brand-red/10 text-brand-red',
            'medium' => 'border-brand-white/20 bg-brand-white/10 text-brand-white',
            'low' => 'border-brand-white/10 bg-brand-white/5 text-brand-white/70',
        ];
    @endphp

    <div class="space-y-6">
        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">Post an Update</h3>
            <form method="POST" action="{{ route('portal.updates.store') }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <x-input-label for="title" :value="__('Update Title')" />
                    <x-text-input id="title" name="title" type="text" required placeholder="Campaign rollout status" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="summary" :value="__('Summary')" />
                    <textarea id="summary" name="summary" rows="4" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required placeholder="Share the latest progress"></textarea>
                    <x-input-error :messages="$errors->get('summary')" class="mt-2" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="status" :value="__('Status')" />
                        <select id="status" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="on_track">On Track</option>
                            <option value="at_risk">At Risk</option>
                            <option value="delayed">Delayed</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="priority" :value="__('Priority')" />
                        <select id="priority" name="priority" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="high">High</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="timeline" :value="__('Timeline')" />
                        <x-text-input id="timeline" name="timeline" type="text" placeholder="Q2 rollout timeline" />
                        <x-input-error :messages="$errors->get('timeline')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="progress" :value="__('Progress (%)')" />
                        <x-text-input id="progress" name="progress" type="number" min="0" max="100" required placeholder="0 - 100" />
                    </div>
                </div>

                <div>
                    <x-input-label for="due_on" :value="__('Target Date')" />
                    <x-text-input id="due_on" name="due_on" type="date" required />
                    <x-input-error :messages="$errors->get('due_on')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="notes" :value="__('Notes (Optional)')" />
                    <textarea id="notes" name="notes" rows="3" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Risks, dependencies, or extra context"></textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <x-primary-button class="w-full justify-center">Share Update</x-primary-button>
            </form>
        </div>

        <div class="space-y-4">
            @forelse ($updates as $update)
                <div class="glass-panel rounded-2xl p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $update->user->name }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-brand-white">{{ $update->title }}</h3>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ ucwords(str_replace('_', ' ', $update->status)) }}</p>
                            <p class="mt-2 text-xl text-brand-white">{{ $update->progress }}%</p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs uppercase tracking-[0.3em] text-brand-ash">
                        <span class="rounded-full border px-3 py-1 {{ $priorityStyles[$update->priority] ?? 'border-brand-white/20' }}">
                            {{ ucfirst($update->priority) }} Priority
                        </span>
                        @if ($update->timeline)
                            <span class="rounded-full border border-brand-white/10 px-3 py-1 text-brand-white/70">{{ $update->timeline }}</span>
                        @endif
                    </div>
                    <div class="mt-3 text-sm text-brand-white/70">{!! $update->summary !!}</div>
                    @if ($update->notes)
                        <div class="mt-2 text-sm text-brand-white/60">Notes: {!! $update->notes !!}</div>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs uppercase tracking-[0.3em] text-brand-ash">
                        <span>{{ $update->created_at->format('M d, Y') }}</span>
                        <span>Target {{ $update->due_on ? $update->due_on->format('M d, Y') : 'TBD' }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-brand-white/60">Fresh slate. Post an update to get started!</p>
            @endforelse

            @if ($updates instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="pt-4">
                    {{ $updates->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>


