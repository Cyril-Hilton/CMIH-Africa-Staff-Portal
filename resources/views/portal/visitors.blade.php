<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Visitor Portal</p>
            <h2 class="text-3xl font-display text-brand-white">Visitor Pre-Ticketing</h2>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_1.5fr]">
            {{-- Schedule Visitor Form --}}
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 h-fit">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🎟️ Schedule a Visitor</h3>
                <form method="POST" action="{{ route('portal.hr.pre-tickets.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="visitor_name" :value="__('Visitor Name *')" />
                        <x-text-input id="visitor_name" name="visitor_name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. John Doe" />
                    </div>
                    <div>
                        <x-input-label for="visitor_company" :value="__('Company / Agency')" />
                        <x-text-input id="visitor_company" name="visitor_company" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Google Inc. (Optional)" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="visitor_email" :value="__('Email')" />
                            <x-text-input id="visitor_email" name="visitor_email" type="email" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="john@example.com" />
                        </div>
                        <div>
                            <x-input-label for="visitor_phone" :value="__('Phone')" />
                            <x-text-input id="visitor_phone" name="visitor_phone" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="+233 XX XXX XXXX" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="purpose" :value="__('Purpose of Visit *')" />
                        <x-text-input id="purpose" name="purpose" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Product Pitch / Meeting" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="host_id" :value="__('Designated Host *')" />
                            <select id="host_id" name="host_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                @foreach($staff as $s)
                                    <option value="{{ $s->id }}" @selected($s->id === auth()->id())>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="expected_arrival" :value="__('Expected Arrival *')" />
                            <x-text-input id="expected_arrival" name="expected_arrival" type="datetime-local" required class="mt-1 w-full" />
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Create Pre-Ticket
                    </button>
                </form>
            </div>

            {{-- Pre-Ticket Ledger --}}
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📋 Your Scheduled Visitors</h3>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[500px] text-left text-xs text-brand-white/70">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                            <tr class="">
                                <th class="font-normal pb-3 text-left">Visitor</th>
                                <th class="font-normal pb-3 text-left">Host</th>
                                <th class="font-normal pb-3 text-left">Purpose</th>
                                <th class="font-normal pb-3 text-left">Expected Arrival</th>
                                <th class="font-normal pb-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @forelse($preTickets as $pt)
                                <tr>
                                    <td class="py-4">
                                        <p class="font-semibold text-brand-white">{{ $pt->visitor_name }}</p>
                                        @if($pt->visitor_company)
                                            <p class="text-[10px] text-brand-white/40">{{ $pt->visitor_company }}</p>
                                        @endif
                                    </td>
                                    <td class="py-4 text-brand-white/80">{{ $pt->host?->name ?? 'Staff' }}</td>
                                    <td class="py-4">{{ $pt->purpose }}</td>
                                    <td class="py-4 font-mono">{{ $pt->expected_arrival?->format('d M Y h:i A') }}</td>
                                    <td class="py-4 text-right">
                                        @if($pt->status === 'arrived')
                                            <span class="inline-block rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[9px] uppercase font-bold text-emerald-400">Arrived</span>
                                        @else
                                            <span class="inline-block rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-[9px] uppercase font-bold text-amber-400">Scheduled</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-xs text-brand-white/40 italic">No pre-tickets scheduled yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($preTickets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $preTickets->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
