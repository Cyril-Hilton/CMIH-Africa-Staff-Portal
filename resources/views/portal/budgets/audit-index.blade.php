<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-semibold">Project Budgets</h1></x-slot>
    <section class="glass-panel space-y-4 rounded-2xl p-5">
        <div class="flex flex-wrap justify-between gap-3">
            <p>Read-only budget register</p>
            <a href="{{ route('portal.finance') }}" class="underline">Back to Finance</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Budget</th><th class="p-3">Created by</th><th class="p-3">Status</th><th class="p-3">Total</th><th class="p-3">Created</th></tr></thead>
                <tbody>
                    @forelse($budgets as $budget)
                        <tr class="border-t border-brand-white/10">
                            <td class="p-3"><a class="underline" href="{{ route('portal.finance.budgets.show', $budget) }}">{{ $budget->title }}</a></td>
                            <td class="p-3">{{ $budget->creator?->name ?? '—' }}</td>
                            <td class="p-3">{{ $budget->status }}</td>
                            <td class="p-3">{{ $budget->currency }} {{ number_format($budget->total_amount, 2) }}</td>
                            <td class="p-3 whitespace-nowrap">{{ $budget->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="5">No project budgets recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $budgets->links() }}
    </section>
</x-app-layout>
