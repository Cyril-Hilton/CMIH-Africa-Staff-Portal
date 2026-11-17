<section class="glass-panel space-y-4 rounded-2xl p-5">
    <h2 class="text-lg font-semibold">Staff Payslips</h2>
    <form method="GET" action="{{ route('portal.payroll') }}" class="flex flex-wrap items-center gap-3">
        <label>Pay period <input type="month" name="period" value="{{ $periodFilter }}" class="rounded-lg bg-brand-black"></label>
        <button class="rounded-lg border border-brand-white/20 px-3 py-2">Filter</button>
        <a href="{{ route('portal.payroll') }}" class="underline">Clear</a>
    </form>
    <ul class="divide-y divide-white/10">
        @forelse($auditedPayslips as $slip)
            <li class="flex flex-wrap justify-between gap-2 py-3 text-sm">
                <span>{{ $slip->user?->name ?? 'Staff record' }} · {{ $slip->period_label }}</span>
                <a class="underline" href="{{ route('portal.payroll.payslip', $slip) }}">Download payslip</a>
            </li>
        @empty
            <li class="py-3">No payslips match the selected period.</li>
        @endforelse
    </ul>
    {{ $auditedPayslips->links() }}
</section>
