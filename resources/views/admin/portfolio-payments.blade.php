<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">System Transactions</p>
            <h2 class="text-3xl font-display text-brand-white">Portfolio Payments</h2>
        </div>
    </x-slot>

    <div class="glass-panel rounded-2xl p-6 bg-brand-black/40 border border-brand-white/10">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-brand-white/80">
                <thead class="border-b border-brand-white/10 text-xs uppercase tracking-wider text-brand-ash">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Item Requested</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-white/5">
                    @forelse ($payments as $payment)
                        <tr x-data="{ expanded: false }" class="hover:bg-brand-white/5 transition-colors">
                            <td class="px-4 py-4 whitespace-nowrap text-xs text-brand-white/60">
                                {{ $payment->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap font-mono text-xs text-brand-white">
                                {{ $payment->reference }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-brand-white text-xs">{{ $payment->name }}</div>
                                <div class="text-[10px] text-brand-white/50">{{ $payment->email }}</div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap font-medium text-brand-red text-xs">
                                {{ $payment->itemLabel() }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-right font-bold text-brand-white">
                                {{ $payment->currency }} {{ number_format($payment->amount, 2) }}
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                @if ($payment->status === 'success')
                                    <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">
                                        Success
                                    </span>
                                @elseif ($payment->status === 'pending')
                                    <span class="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300">
                                        Pending
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border border-red-500/30 bg-red-500/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-red-300">
                                        Failed
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <button 
                                    @click="expanded = !expanded" 
                                    class="rounded-md border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-xs text-brand-white hover:bg-brand-white/10 hover:text-white transition-colors"
                                >
                                    <span x-text="expanded ? 'Hide' : 'Show'"></span>
                                </button>
                            </td>
                        </tr>
                        
                        {{-- Expandable Detail Panel --}}
                        <tr x-show="expanded" x-cloak style="display: none;" class="bg-brand-black/60">
                            <td colspan="7" class="px-6 py-4">
                                <div class="grid gap-6 md:grid-cols-2 text-xs">
                                    <div class="space-y-2">
                                        <h4 class="font-bold text-brand-red uppercase tracking-wider">Brief Description / Requirements</h4>
                                        <p class="text-brand-white/80 whitespace-pre-wrap leading-relaxed bg-brand-black/40 p-3 rounded-lg border border-brand-white/5">
                                            {{ $payment->description ?: 'No description provided.' }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <h4 class="font-bold text-brand-white/70 uppercase tracking-wider">Raw Paystack Response</h4>
                                        <pre class="overflow-x-auto rounded-lg border border-brand-white/5 bg-brand-black/80 p-3 text-[10px] font-mono text-emerald-400 max-h-48 scrollbar-thin"><code>@if($payment->raw_response){{ json_encode(json_decode($payment->raw_response), JSON_PRETTY_PRINT) }}@else{{ 'No response payload.' }}@endif</code></pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-brand-white/60">
                                No payments or requests found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-6">
            {{ $payments->links() }}
        </div>
    </div>
</x-app-layout>
