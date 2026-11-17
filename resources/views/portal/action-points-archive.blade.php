<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Completed Action Points</h1>
            <a class="underline" href="{{ route('dashboard', ['action_points' => 1]) }}#action-points-disclosure">Back to active actions</a>
        </div>
    </x-slot>
    <section class="glass-panel rounded-2xl p-5">
        @if(session('status')) <p role="status" class="mb-3 text-sm text-emerald-300">{{ session('status') }}</p> @endif
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr>@foreach(['Action point','Assignee','Comments','Last updated',''] as $label)<th scope="col" class="p-3">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($points as $point)
                    <tr class="border-t border-brand-white/10 align-top">
                        <th scope="row" class="whitespace-pre-wrap p-3 font-semibold">{{ $point->action_point }}</th>
                        <td class="p-3">{{ $point->display_assignee }}</td>
                        <td class="whitespace-pre-wrap p-3">{{ $point->comments ?: '—' }}</td>
                        <td class="whitespace-nowrap p-3">{{ $point->updated_at?->format('d M Y') ?? '—' }}</td>
                        <td class="p-3">
                            <form method="POST" action="{{ route('portal.action-points.update', $point) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="in_progress">
                                <button class="rounded-lg border border-brand-white/20 px-3 py-2 text-sm">Reopen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-4 text-brand-ash" colspan="5">Completed action points will appear here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $points->links() }}</div>
    </section>
</x-app-layout>
