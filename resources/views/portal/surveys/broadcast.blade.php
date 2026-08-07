<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('portal.surveys.show', $survey) }}"
               class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-brand-white/15 hover:border-brand-white/40 bg-brand-white/5 hover:bg-brand-white/10 px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-white/70 hover:text-brand-white transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
            </a>
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portal · Surveys · {{ Str::limit($survey->title, 40) }}</p>
                <h2 class="text-3xl font-display text-brand-white">📣 Broadcast Email</h2>
            </div>
        </div>
    </x-slot>

    @if($survey->is_anonymous)
        <div class="rounded-2xl border border-brand-red/30 bg-brand-red/10 p-6 text-center">
            <p class="text-sm font-semibold text-brand-red mb-1">Anonymous Survey — No Emails Available</p>
            <p class="text-xs text-brand-white/60">This survey was set to anonymous mode, so no email addresses were collected. You cannot broadcast to anonymous respondents.</p>
        </div>
    @else

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
            <p class="font-semibold mb-1 text-brand-red">Please fix the following:</p>
            <ul class="list-disc pl-5 text-xs text-brand-white/70">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Recipient Stats Bar --}}
    <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5 flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-full bg-brand-red/10 border border-brand-red/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wider text-brand-ash">Total Recipients</p>
                <p class="text-3xl font-display text-brand-white">{{ $recipientCount }}</p>
            </div>
        </div>
        <div class="text-xs text-brand-white/40 max-w-sm text-right">
            Emails will be sent individually to each respondent with their name personalised.
            Use <code class="text-brand-red bg-brand-red/10 px-1 py-0.5 rounded">{name}</code> in your message body and it will be replaced with each person's name.
        </div>
    </div>

    <form method="POST" action="{{ route('portal.surveys.broadcast.send', $survey) }}" x-data="broadcastForm()" @submit.prevent="confirmAndSend">
        @csrf

        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">

            {{-- ── LEFT COLUMN: MESSAGE COMPOSER ─────────────────────── --}}
            <div class="space-y-6">

                {{-- Subject --}}
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-4">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white border-b border-brand-white/10 pb-3">✉️ Email Content</h3>

                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1" for="subject">Subject Line *</label>
                        <x-text-input id="subject" name="subject" type="text" required
                            class="w-full border border-brand-white/10 bg-brand-black/40 text-brand-white"
                            placeholder="e.g. 🎉 Congratulations — You've Been Selected!"
                            value="{{ old('subject') }}" />
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1" for="body">
                            Message Body *
                            <span class="ml-2 normal-case text-brand-white/30 tracking-normal">Use <code class="text-brand-red">{name}</code> to personalise per recipient</span>
                        </label>
                        <textarea id="body" name="body" rows="12" required x-model="body"
                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20 font-mono"
                            placeholder="Hi {name},&#10;&#10;Congratulations! You have been selected to attend our upcoming event.&#10;&#10;Kindly find the event details on the right.&#10;&#10;We look forward to seeing you there!&#10;&#10;Warm regards,&#10;The CMIH Team">{{ old('body') }}</textarea>
                        <p class="text-[9px] text-brand-white/30 mt-1">Characters: <span x-text="body.length"></span> / 10,000</p>
                    </div>
                </div>

                {{-- Recipients Selection --}}
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                    <div class="flex items-center justify-between border-b border-brand-white/10 pb-3 mb-4">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">👥 Recipients</h3>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="selectAll(true)" class="text-[10px] text-emerald-400 hover:text-emerald-300 uppercase tracking-wider font-semibold">Select All</button>
                            <span class="text-brand-white/20">|</span>
                            <button type="button" onclick="selectAll(false)" class="text-[10px] text-brand-red hover:text-brand-red-dark uppercase tracking-wider font-semibold">Deselect All</button>
                        </div>
                    </div>
                    <p class="text-[11px] text-brand-white/40 mb-4">Leave all <strong class="text-brand-white/60">unchecked</strong> to send to ALL respondents, or manually check specific ones.</p>

                    <div class="max-h-72 overflow-y-auto space-y-1 pr-1 scrollbar-none" id="recipientList">
                        @forelse($survey->responses->whereNotNull('email') as $r)
                            <label class="flex items-center gap-3 cursor-pointer rounded-lg hover:bg-brand-white/5 px-3 py-2 transition group">
                                <input type="checkbox" name="recipient_ids[]" value="{{ $r->id }}"
                                    class="h-4 w-4 rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-0 focus:ring-offset-0">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-brand-white font-medium truncate">{{ $r->name ?? 'Anonymous' }}</p>
                                    <p class="text-[10px] text-brand-white/40 truncate">{{ $r->email }}</p>
                                </div>
                                <span class="text-[9px] text-brand-white/20 shrink-0">{{ $r->created_at->format('d M') }}</span>
                            </label>
                        @empty
                            <p class="text-xs text-brand-white/30 italic text-center py-4">No respondents with email addresses found.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ── RIGHT COLUMN: EVENT DETAILS & SEND ─────────────────── --}}
            <div class="space-y-6">

                {{-- Event Details --}}
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-4">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white border-b border-brand-white/10 pb-3">📅 Event Details <span class="text-brand-ash font-normal normal-case text-xs">(Optional)</span></h3>
                    <p class="text-[11px] text-brand-white/40">These appear as a highlighted details card in the email body.</p>

                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1">Date</label>
                        <x-text-input name="event_date" type="text"
                            class="w-full border border-brand-white/10 bg-brand-black/40 text-brand-white"
                            placeholder="e.g. Saturday, July 5, 2025"
                            value="{{ old('event_date', $survey->event?->starts_at?->format('l, F j, Y') ?? '') }}" />
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1">Time</label>
                        <x-text-input name="event_time" type="text"
                            class="w-full border border-brand-white/10 bg-brand-black/40 text-brand-white"
                            placeholder="e.g. 7:00 PM GMT"
                            value="{{ old('event_time') }}" />
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1">Location / Venue</label>
                        <x-text-input name="event_location" type="text"
                            class="w-full border border-brand-white/10 bg-brand-black/40 text-brand-white"
                            placeholder="e.g. 4Syte TV Studios, Accra"
                            value="{{ old('event_location', $survey->location_label ?? $survey->event?->location ?? '') }}" />
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1">Google Maps URL</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-brand-white/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <input id="event_map_url" name="event_map_url" type="text"
                                class="w-full rounded-md border border-brand-white/10 bg-brand-black/40 pl-9 pr-4 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20"
                                placeholder="Search or paste a Google Maps link..."
                                value="{{ old('event_map_url', $survey->location_url ?? '') }}"
                                autocomplete="off">
                        </div>
                    </div>
                </div>

                {{-- Email Preview Card --}}
                <div class="glass-panel rounded-2xl p-6 border border-emerald-500/20 bg-emerald-500/5 space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-emerald-400">📬 What Recipients See</h3>
                    <div class="space-y-2 text-xs text-brand-white/60">
                        <p>✅ <strong class="text-brand-white/80">From:</strong> support@cmih.africa</p>
                        <p>✅ <strong class="text-brand-white/80">Personalised:</strong> Each email says "Hello [their name]"</p>
                        <p>✅ <strong class="text-brand-white/80">Cost:</strong> Completely free — uses CMIH mail server</p>
                        <p>✅ <strong class="text-brand-white/80">Branded:</strong> Dark CMIH-styled HTML email</p>
                    </div>
                </div>

                {{-- Send Button --}}
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-brand-ash">Ready to send?</p>
                            <p class="text-sm text-brand-white font-semibold">
                                <span id="selectedCount">All {{ $recipientCount }}</span> recipients
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-brand-red/10 border border-brand-red/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-3 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition shadow-lg flex items-center justify-center gap-2"
                        :disabled="sending" x-text="sending ? 'Sending emails...' : '📣 Send Broadcast'">
                        📣 Send Broadcast
                    </button>

                    <p class="text-[9px] text-brand-white/30 text-center">Emails are sent immediately. This action cannot be undone.</p>
                </div>

            </div>
        </div>

        {{-- Confirm Modal --}}
        <div x-show="showConfirm" x-transition
             class="fixed inset-0 z-50 flex items-center justify-center bg-brand-black/80 backdrop-blur-sm p-4">
            <div class="glass-panel w-full max-w-md rounded-2xl border border-brand-white/15 bg-brand-black/80 p-8 shadow-2xl space-y-5 text-center">
                <div class="h-16 w-16 rounded-full bg-brand-red/10 border border-brand-red/20 flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-display text-brand-white">Send Broadcast?</h3>
                    <p class="text-sm text-brand-white/60 mt-2">
                        You are about to send an email to <strong class="text-brand-white" x-text="recipientLabel"></strong>.
                        This cannot be undone.
                    </p>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="showConfirm = false"
                        class="flex-1 rounded-xl border border-brand-white/20 hover:bg-brand-white/5 py-2.5 text-xs font-semibold uppercase tracking-wider text-brand-white transition">
                        Cancel
                    </button>
                    <button type="button" @click="doSend"
                        class="flex-1 rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs font-semibold uppercase tracking-wider text-brand-white transition"
                        :disabled="sending">
                        Yes, Send Now
                    </button>
                </div>
            </div>
        </div>

    </form>
    @endif

@push('scripts')
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places&callback=initMapAC" async defer></script>
<script>
function initMapAC() {
    const input = document.getElementById('event_map_url');
    if (!input) return;
    const ac = new google.maps.places.Autocomplete(input, { types: ['establishment', 'geocode'] });
    ac.addListener('place_changed', function() {
        const place = ac.getPlace();
        if (place.url) input.value = place.url;
        else if (place.geometry) {
            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();
            input.value = `https://www.google.com/maps?q=${lat},${lng}`;
        }
        // Auto-fill location field if empty
        const locInput = document.querySelector('input[name="event_location"]');
        if (locInput && !locInput.value && place.name) locInput.value = place.name;
    });
}

function selectAll(checked) {
    document.querySelectorAll('#recipientList input[type="checkbox"]').forEach(cb => {
        cb.checked = checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('#recipientList input[type="checkbox"]:checked');
    const el = document.getElementById('selectedCount');
    if (!el) return;
    const total = {{ $recipientCount }};
    el.textContent = checked.length > 0 ? `${checked.length} selected of ${total}` : `All ${total}`;
}

document.querySelectorAll('#recipientList input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

function broadcastForm() {
    return {
        body: document.getElementById('body')?.value || '',
        showConfirm: false,
        sending: false,
        recipientLabel: '',
        confirmAndSend() {
            const checked = document.querySelectorAll('#recipientList input[type="checkbox"]:checked');
            const total = {{ $recipientCount }};
            this.recipientLabel = checked.length > 0
                ? `${checked.length} selected recipient(s)`
                : `all ${total} respondents`;
            this.showConfirm = true;
        },
        doSend() {
            this.sending = true;
            this.showConfirm = false;
            this.$el.submit();
        }
    };
}
</script>
@endpush

</x-app-layout>
