<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">HRM Tools</p>
            <h2 class="text-3xl font-display text-brand-white">Fleet Requests</h2>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-brand-red/30 bg-brand-red/10 p-4 text-sm text-red-200">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6">
            <div class="mb-5">
                <p class="text-xs uppercase tracking-[0.25em] text-brand-ash">New Request</p>
                <h3 class="mt-1 text-xl font-semibold text-brand-white">Transport Assistance</h3>
            </div>

            <form method="POST" action="{{ route('portal.fleet-requests.store') }}" class="grid gap-4 lg:grid-cols-2" x-data="{ type: '{{ old('assistance_type', 'company_vehicle') }}' }">
                @csrf

                <div>
                    <x-input-label for="assistance_type" :value="__('Assistance Type')" />
                    <select id="assistance_type" name="assistance_type" x-model="type" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                        <option value="company_vehicle">Company Vehicle</option>
                        <option value="ride_hailing">Ride Hailing</option>
                    </select>
                    <x-input-error :messages="$errors->get('assistance_type')" class="mt-2" />
                </div>

                <div x-show="type === 'company_vehicle'">
                    <x-input-label for="company_vehicle_option" :value="__('Company Vehicle')" />
                    <select id="company_vehicle_option" name="vehicle_option" x-bind:disabled="type !== 'company_vehicle'" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                        @foreach($companyVehicles as $value => $label)
                            <option value="{{ $value }}" @selected(old('vehicle_option') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('vehicle_option')" class="mt-2" />
                </div>

                <div x-show="type === 'ride_hailing'" style="display: none;">
                    <x-input-label for="ride_hailing_option" :value="__('Ride Hailing')" />
                    <select id="ride_hailing_option" name="vehicle_option" x-bind:disabled="type !== 'ride_hailing'" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                        @foreach($rideHailingOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('vehicle_option') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('vehicle_option')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="pickup_location" :value="__('Pickup Location')" />
                    <x-text-input id="pickup_location" name="pickup_location" type="text" :value="old('pickup_location')" placeholder="Where should the trip start?" class="mt-1 w-full" required />
                    <x-input-error :messages="$errors->get('pickup_location')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="destination" :value="__('Destination')" />
                    <x-text-input id="destination" name="destination" type="text" :value="old('destination')" placeholder="Where are you going?" class="mt-1 w-full" required />
                    <x-input-error :messages="$errors->get('destination')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="requested_date" :value="__('Trip Date')" />
                    <x-text-input id="requested_date" name="requested_date" type="date" :value="old('requested_date')" class="mt-1 w-full" required />
                    <x-input-error :messages="$errors->get('requested_date')" class="mt-2" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="requested_time" :value="__('Time')" />
                        <x-text-input id="requested_time" name="requested_time" type="time" :value="old('requested_time')" class="mt-1 w-full" />
                        <x-input-error :messages="$errors->get('requested_time')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="passengers" :value="__('Passengers')" />
                        <x-text-input id="passengers" name="passengers" type="number" min="1" max="50" :value="old('passengers', 1)" class="mt-1 w-full" required />
                        <x-input-error :messages="$errors->get('passengers')" class="mt-2" />
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <x-input-label for="purpose" :value="__('Purpose')" />
                    <textarea id="purpose" name="purpose" rows="4" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red" placeholder="Client meeting, field visit, asset pickup, staff movement, etc.">{{ old('purpose') }}</textarea>
                    <x-input-error :messages="$errors->get('purpose')" class="mt-2" />
                </div>

                <div class="lg:col-span-2">
                    <x-input-label for="notes" :value="__('Additional Notes')" />
                    <textarea id="notes" name="notes" rows="3" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red" placeholder="Optional">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>

                <div class="lg:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-xl bg-brand-red px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>

        @if($pendingRequests)
            <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-brand-white">HR Review Queue</h3>
                    <span class="text-xs uppercase tracking-[0.2em] text-brand-ash">{{ $pendingRequests->total() }} total</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-left text-sm text-brand-white/70">
                        <thead class="border-b border-brand-white/10 text-xs uppercase tracking-[0.2em] text-brand-ash">
                            <tr>
                                <th class="pb-3">Staff</th>
                                <th class="pb-3">Request</th>
                                <th class="pb-3">Trip</th>
                                <th class="pb-3">Purpose</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">HR Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingRequests as $fleetRequest)
                                <tr class="border-b border-brand-white/5 align-top">
                                    <td class="py-4 font-medium text-brand-white">{{ $fleetRequest->user?->name ?? 'Unknown' }}</td>
                                    <td class="py-4">
                                        <p class="text-brand-white">{{ $fleetRequest->optionLabel() }}</p>
                                        <p class="text-xs text-brand-white/40">{{ str_replace('_', ' ', $fleetRequest->assistance_type) }}</p>
                                    </td>
                                    <td class="py-4">
                                        <p>{{ $fleetRequest->pickup_location }} to {{ $fleetRequest->destination }}</p>
                                        <p class="text-xs text-brand-white/40">{{ $fleetRequest->requested_date?->format('M d, Y') }} {{ $fleetRequest->requested_time }}</p>
                                    </td>
                                    <td class="py-4 max-w-xs">
                                        <p class="line-clamp-3">{{ $fleetRequest->purpose }}</p>
                                        @if($fleetRequest->notes)
                                            <p class="mt-1 text-xs text-brand-white/40">{{ $fleetRequest->notes }}</p>
                                        @endif
                                        @if($fleetRequest->hr_comment)
                                            <p class="mt-2 text-xs text-amber-300">HR: {{ $fleetRequest->hr_comment }}</p>
                                        @endif
                                    </td>
                                    <td class="py-4 capitalize">{{ str_replace('_', ' ', $fleetRequest->status) }}</td>
                                    <td class="py-4">
                                        <form method="POST" action="{{ route('portal.fleet-requests.action', $fleetRequest) }}" class="min-w-[18rem] space-y-2">
                                            @csrf
                                            <textarea name="hr_comment" rows="2" class="w-full rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:outline-none" placeholder="Comment or correction note">{{ old('hr_comment') }}</textarea>
                                            <div class="flex flex-wrap gap-2">
                                                <button name="action" value="approve" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Approve</button>
                                                <button name="action" value="return" class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-200">Return</button>
                                                <button name="action" value="reject" class="rounded-lg border border-brand-red/30 bg-brand-red/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-red">Reject</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-10 text-center text-sm text-brand-white/40">No fleet requests yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $pendingRequests->links() }}</div>
            </div>
        @endif

        <div class="glass-panel rounded-2xl border border-brand-white/10 bg-brand-white/5 p-6">
            <h3 class="mb-4 text-lg font-semibold text-brand-white">My Fleet Requests</h3>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm text-brand-white/70">
                    <thead class="border-b border-brand-white/10 text-xs uppercase tracking-[0.2em] text-brand-ash">
                        <tr>
                            <th class="pb-3">Option</th>
                            <th class="pb-3">Trip</th>
                            <th class="pb-3">Purpose</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">HR Comment</th>
                            <th class="pb-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myRequests as $fleetRequest)
                            <tr class="border-b border-brand-white/5 align-top">
                                <td class="py-4 font-medium text-brand-white">{{ $fleetRequest->optionLabel() }}</td>
                                <td class="py-4">
                                    <p>{{ $fleetRequest->pickup_location }} to {{ $fleetRequest->destination }}</p>
                                    <p class="text-xs text-brand-white/40">{{ $fleetRequest->requested_date?->format('M d, Y') }} {{ $fleetRequest->requested_time }}</p>
                                </td>
                                <td class="py-4 max-w-xs">{{ $fleetRequest->purpose }}</td>
                                <td class="py-4 capitalize">{{ str_replace('_', ' ', $fleetRequest->status) }}</td>
                                <td class="py-4">{{ $fleetRequest->hr_comment ?: 'None' }}</td>
                                <td class="py-4">
                                    @if($fleetRequest->status === 'returned_for_correction')
                                        <span class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-amber-200">Correction needed</span>
                                    @else
                                        <span class="text-xs text-brand-white/35">No action</span>
                                    @endif
                                </td>
                            </tr>
                            @if($fleetRequest->status === 'returned_for_correction')
                                <tr class="border-b border-brand-white/10 bg-brand-black/30">
                                    <td colspan="6" class="py-5">
                                        <form method="POST" action="{{ route('portal.fleet-requests.resubmit', $fleetRequest) }}" class="grid gap-4 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 lg:grid-cols-2" x-data="{ type: '{{ $fleetRequest->assistance_type }}' }">
                                            @csrf

                                            <div>
                                                <x-input-label :value="__('Assistance Type')" />
                                                <select name="assistance_type" x-model="type" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                                                    <option value="company_vehicle">Company Vehicle</option>
                                                    <option value="ride_hailing">Ride Hailing</option>
                                                </select>
                                            </div>

                                            <div x-show="type === 'company_vehicle'">
                                                <x-input-label :value="__('Company Vehicle')" />
                                                <select name="vehicle_option" x-bind:disabled="type !== 'company_vehicle'" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                                                    @foreach($companyVehicles as $value => $label)
                                                        <option value="{{ $value }}" @selected($fleetRequest->vehicle_option === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div x-show="type === 'ride_hailing'" style="display: none;">
                                                <x-input-label :value="__('Ride Hailing')" />
                                                <select name="vehicle_option" x-bind:disabled="type !== 'ride_hailing'" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                                                    @foreach($rideHailingOptions as $value => $label)
                                                        <option value="{{ $value }}" @selected($fleetRequest->vehicle_option === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <x-input-label :value="__('Pickup Location')" />
                                                <x-text-input name="pickup_location" type="text" :value="$fleetRequest->pickup_location" class="mt-1 w-full" required />
                                            </div>

                                            <div>
                                                <x-input-label :value="__('Destination')" />
                                                <x-text-input name="destination" type="text" :value="$fleetRequest->destination" class="mt-1 w-full" required />
                                            </div>

                                            <div>
                                                <x-input-label :value="__('Trip Date')" />
                                                <x-text-input name="requested_date" type="date" :value="$fleetRequest->requested_date?->toDateString()" class="mt-1 w-full" required />
                                            </div>

                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <x-input-label :value="__('Time')" />
                                                    <x-text-input name="requested_time" type="time" :value="$fleetRequest->requested_time" class="mt-1 w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label :value="__('Passengers')" />
                                                    <x-text-input name="passengers" type="number" min="1" max="50" :value="$fleetRequest->passengers" class="mt-1 w-full" required />
                                                </div>
                                            </div>

                                            <div class="lg:col-span-2">
                                                <x-input-label :value="__('Purpose')" />
                                                <textarea name="purpose" rows="3" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">{{ $fleetRequest->purpose }}</textarea>
                                            </div>

                                            <div class="lg:col-span-2">
                                                <x-input-label :value="__('Additional Notes')" />
                                                <textarea name="notes" rows="2" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">{{ $fleetRequest->notes }}</textarea>
                                            </div>

                                            <div class="lg:col-span-2 flex justify-end">
                                                <button type="submit" class="rounded-xl bg-amber-500 px-6 py-2.5 text-xs font-bold uppercase tracking-[0.2em] text-black hover:bg-amber-400">
                                                    Resubmit Correction
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="py-10 text-center text-sm text-brand-white/40">You have not submitted a fleet request yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $myRequests->links() }}</div>
        </div>
    </div>
</x-app-layout>
