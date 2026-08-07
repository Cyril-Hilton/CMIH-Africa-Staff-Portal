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
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portal · Surveys</p>
                <h2 class="text-3xl font-display text-brand-white">Edit Survey</h2>
            </div>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
            <p class="font-semibold mb-1 text-brand-red">Please correct the following errors:</p>
            <ul class="list-disc pl-5 text-xs text-brand-white/70">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.surveys.update', $survey) }}" class="space-y-6" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- ── 1. SURVEY DETAILS ──────────────────────────────────────── --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📝 Survey Details</h3>
            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-4">
                    <div>
                        <x-input-label for="title" :value="__('Survey Title *')" />
                        <x-text-input id="title" name="title" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white" value="{{ old('title', $survey->title) }}" />
                    </div>
                    <div>
                        <x-input-label for="description" :value="__('Description / Welcome Message')" />
                        <textarea id="description" name="description" rows="4" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/30">{{ old('description', $survey->description) }}</textarea>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <x-input-label for="event_id" :value="__('Link to Activation / Event (Optional)')" />
                        <select id="event_id" name="event_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                            <option value="">-- No linked event --</option>
                            @foreach($events as $e)
                                <option value="{{ $e->id }}" @selected(old('event_id', $survey->event_id) == $e->id)>{{ $e->title }} ({{ $e->starts_at->format('M d, Y') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                <option value="published" @selected(old('status', $survey->status) === 'published')>Published (Open)</option>
                                <option value="draft" @selected(old('status', $survey->status) === 'draft')>Draft</option>
                                <option value="closed" @selected(old('status', $survey->status) === 'closed')>Closed</option>
                            </select>
                        </div>
                        <div>
                            <span class="block text-sm font-medium text-brand-white/80 mb-2">Survey Mode</span>
                            <label class="inline-flex items-center cursor-pointer mt-1">
                                <input type="checkbox" name="is_anonymous" value="1" class="sr-only peer" @checked(old('is_anonymous', $survey->is_anonymous) == 1)>
                                <div class="relative w-11 h-6 bg-brand-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-brand-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-red"></div>
                                <span class="ms-3 text-xs font-semibold uppercase tracking-wider text-brand-white/70">Anonymous</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 2. LOGO BRANDING ────────────────────────────────────────── --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-1">🎨 Logo Branding <span class="text-brand-ash font-normal normal-case text-xs">(Optional)</span></h3>
            <p class="text-[11px] text-brand-white/40 mb-5">Logos appear side-by-side at the top of the public survey.</p>

            <div class="grid gap-6 sm:grid-cols-3">

                {{-- CMIH Logo --}}
                <div class="space-y-2">
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium">CMIH Logo <span class="text-brand-white/30">(Left side)</span></label>
                    @if($survey->cmih_logo_path)
                        <div class="flex items-center gap-3 rounded-lg bg-brand-black/40 border border-emerald-500/30 px-3 py-2 mb-2" id="cmih_existing">
                            <img src="{{ Storage::url($survey->cmih_logo_path) }}" alt="CMIH Logo" class="h-10 w-auto object-contain max-w-[80px]">
                            <span class="text-[10px] text-brand-white/50 flex-1">Current logo</span>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="remove_cmih_logo" value="1"
                                       class="rounded border-brand-white/20 bg-transparent text-brand-red focus:ring-0"
                                       onchange="toggleRemove('cmih_existing','cmih_upload_area',this.checked)">
                                <span class="text-[10px] text-brand-red">Remove</span>
                            </label>
                        </div>
                    @endif
                    <div id="cmih_upload_area" class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-white/15 bg-brand-black/30 p-5 hover:border-brand-red/50 transition cursor-pointer"
                         onclick="document.getElementById('cmih_logo').click()">
                        <svg class="w-7 h-7 text-brand-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-[10px] text-brand-white/40">{{ $survey->cmih_logo_path ? 'Click to replace' : 'Click to upload' }}</span>
                        <input id="cmih_logo" name="cmih_logo" type="file" accept="image/*" class="hidden" onchange="previewLogo(this,'cmih_preview_new')">
                    </div>
                    <div id="cmih_preview_new" class="hidden flex items-center gap-2 rounded-lg bg-brand-black/40 border border-brand-white/10 px-3 py-2">
                        <img src="" alt="" class="h-10 w-auto object-contain max-w-[80px]">
                        <p class="text-[10px] text-brand-white/70 flex-1 truncate logo-filename"></p>
                        <button type="button" onclick="clearLogo('cmih_logo','cmih_preview_new')" class="text-brand-red text-xs">✕</button>
                    </div>
                </div>

                {{-- Brand 1 --}}
                <div class="space-y-2">
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium">Brand 1 Name</label>
                    <x-text-input id="client_brand_name" name="client_brand_name" type="text"
                        class="w-full border border-brand-white/10 bg-brand-black/40 text-brand-white"
                        placeholder="e.g. Guinness" value="{{ old('client_brand_name', $survey->client_brand_name) }}" />
                    @if($survey->client_logo_path)
                        <div class="flex items-center gap-3 rounded-lg bg-brand-black/40 border border-emerald-500/30 px-3 py-2 mb-2" id="client1_existing">
                            <img src="{{ Storage::url($survey->client_logo_path) }}" alt="" class="h-10 w-auto object-contain max-w-[80px]">
                            <span class="text-[10px] text-brand-white/50 flex-1">Current logo</span>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="remove_client_logo" value="1"
                                       class="rounded border-brand-white/20 bg-transparent text-brand-red focus:ring-0"
                                       onchange="toggleRemove('client1_existing','client1_upload_area',this.checked)">
                                <span class="text-[10px] text-brand-red">Remove</span>
                            </label>
                        </div>
                    @endif
                    <div id="client1_upload_area" class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-white/15 bg-brand-black/30 p-5 hover:border-brand-red/50 transition cursor-pointer"
                         onclick="document.getElementById('client_logo').click()">
                        <svg class="w-7 h-7 text-brand-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-[10px] text-brand-white/40">{{ $survey->client_logo_path ? 'Click to replace' : 'Click to upload' }}</span>
                        <input id="client_logo" name="client_logo" type="file" accept="image/*" class="hidden" onchange="previewLogo(this,'client1_preview_new')">
                    </div>
                    <div id="client1_preview_new" class="hidden flex items-center gap-2 rounded-lg bg-brand-black/40 border border-brand-white/10 px-3 py-2">
                        <img src="" alt="" class="h-10 w-auto object-contain max-w-[80px]">
                        <p class="text-[10px] text-brand-white/70 flex-1 truncate logo-filename"></p>
                        <button type="button" onclick="clearLogo('client_logo','client1_preview_new')" class="text-brand-red text-xs">✕</button>
                    </div>
                </div>

                {{-- Brand 2 --}}
                <div class="space-y-2">
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium">Brand 2 Name <span class="text-brand-white/30">(Optional)</span></label>
                    <x-text-input id="client_brand_name_2" name="client_brand_name_2" type="text"
                        class="w-full border border-brand-white/10 bg-brand-black/40 text-brand-white"
                        placeholder="e.g. Coca-Cola" value="{{ old('client_brand_name_2', $survey->client_brand_name_2) }}" />
                    @if($survey->client_logo_path_2)
                        <div class="flex items-center gap-3 rounded-lg bg-brand-black/40 border border-emerald-500/30 px-3 py-2 mb-2" id="client2_existing">
                            <img src="{{ Storage::url($survey->client_logo_path_2) }}" alt="" class="h-10 w-auto object-contain max-w-[80px]">
                            <span class="text-[10px] text-brand-white/50 flex-1">Current logo</span>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="checkbox" name="remove_client_logo_2" value="1"
                                       class="rounded border-brand-white/20 bg-transparent text-brand-red focus:ring-0"
                                       onchange="toggleRemove('client2_existing','client2_upload_area',this.checked)">
                                <span class="text-[10px] text-brand-red">Remove</span>
                            </label>
                        </div>
                    @endif
                    <div id="client2_upload_area" class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-white/15 bg-brand-black/30 p-5 hover:border-brand-red/50 transition cursor-pointer"
                         onclick="document.getElementById('client_logo_2').click()">
                        <svg class="w-7 h-7 text-brand-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-[10px] text-brand-white/40">{{ $survey->client_logo_path_2 ? 'Click to replace' : 'Click to upload' }}</span>
                        <input id="client_logo_2" name="client_logo_2" type="file" accept="image/*" class="hidden" onchange="previewLogo(this,'client2_preview_new')">
                    </div>
                    <div id="client2_preview_new" class="hidden flex items-center gap-2 rounded-lg bg-brand-black/40 border border-brand-white/10 px-3 py-2">
                        <img src="" alt="" class="h-10 w-auto object-contain max-w-[80px]">
                        <p class="text-[10px] text-brand-white/70 flex-1 truncate logo-filename"></p>
                        <button type="button" onclick="clearLogo('client_logo_2','client2_preview_new')" class="text-brand-red text-xs">✕</button>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── 3. CUSTOM SUCCESS MESSAGE ───────────────────────────────── --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-1">✅ Custom Confirmation Message <span class="text-brand-ash font-normal normal-case text-xs">(Optional)</span></h3>
            <p class="text-[11px] text-brand-white/40 mb-4">Shown to the consumer after submitting. Leave blank to use the default thank-you message.</p>
            <textarea id="success_message" name="success_message" rows="6"
                class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20"
                placeholder="e.g. Thank you! Your RSVP has been submitted. We'll send your entry pass via WhatsApp before kickoff.">{{ old('success_message', $survey->success_message) }}</textarea>
        </div>

        {{-- ── 4. LOCATION TOGGLE ──────────────────────────────────────── --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5"
             x-data="{ locationOn: {{ old('location_enabled', $survey->location_enabled) ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between mb-1">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">📍 Location <span class="text-brand-ash font-normal normal-case text-xs">(Optional)</span></h3>
                    <p class="text-[11px] text-brand-white/40 mt-0.5">When enabled, the event location appears on the confirmation page.</p>
                </div>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="location_enabled" value="1" class="sr-only peer" x-model="locationOn">
                    <div class="relative w-11 h-6 bg-brand-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-brand-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-red"></div>
                    <span class="ms-3 text-xs font-semibold uppercase tracking-wider text-brand-white/70">Enable Location</span>
                </label>
            </div>

            <div x-show="locationOn" x-transition class="mt-5 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1" for="location_label">Location Name / Venue</label>
                    <x-text-input id="location_label" name="location_label" type="text"
                        class="w-full border border-brand-white/10 bg-brand-black/40 text-brand-white"
                        placeholder="e.g. 4Syte TV Studios, Accra"
                        value="{{ old('location_label', $survey->location_label) }}" />
                </div>
                <div>
                    <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1" for="location_url">Google Maps Location</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-brand-white/30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </span>
                        <input id="location_url" name="location_url" type="text"
                            class="w-full rounded-md border border-brand-white/10 bg-brand-black/40 pl-9 pr-4 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20"
                            placeholder="Search or paste a Google Maps link..."
                            value="{{ old('location_url', $survey->location_url) }}"
                            autocomplete="off">
                    </div>
                    <p class="text-[9px] text-brand-white/30 mt-1">Type a location and select from dropdown, or paste a Maps URL directly.</p>
                </div>
            </div>
        </div>

        {{-- ── 5. QUESTION BUILDER ─────────────────────────────────────── --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5"
             x-data="surveyEditBuilder()">

            <div class="flex items-center justify-between border-b border-brand-white/10 pb-3 mb-6">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">🛠️ Question Builder</h3>
                <button type="button" @click="addQuestion()" class="rounded-lg border border-brand-white/20 hover:border-brand-red px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-brand-white/80 hover:text-brand-red transition">
                    + Add Question
                </button>
            </div>

            <div class="space-y-6">
                <template x-for="(q, qIndex) in questions" :key="qIndex">
                    <div class="rounded-xl border border-brand-white/10 bg-brand-black/35 p-5 relative space-y-4">
                        <input type="hidden" :name="'questions[' + qIndex + '][id]'" x-model="q.id">

                        <div class="absolute top-4 right-4 flex items-center gap-2">
                            <button type="button" @click="moveUp(qIndex)" :disabled="qIndex === 0" class="text-brand-white/40 hover:text-brand-white disabled:opacity-20 text-xs">▲</button>
                            <button type="button" @click="moveDown(qIndex)" :disabled="qIndex === questions.length - 1" class="text-brand-white/40 hover:text-brand-white disabled:opacity-20 text-xs">▼</button>
                            <button type="button" @click="removeQuestion(qIndex)" class="text-brand-red hover:text-brand-red-dark ml-2 text-xs font-semibold uppercase tracking-wider">Remove</button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-[1fr_220px]">
                            <div>
                                <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1">Question Prompt</label>
                                <input type="text" :name="'questions[' + qIndex + '][question_text]'" x-model="q.question_text" required
                                    class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1">Response Type</label>
                                <select :name="'questions[' + qIndex + '][question_type]'" x-model="q.question_type"
                                    class="w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                    <option value="short_text">Short Answer</option>
                                    <option value="paragraph">Paragraph</option>
                                    <option value="radio">Multiple Choice — Pick ONE (Radio)</option>
                                    <option value="checkbox">Multiple Select — Pick MANY (Checkboxes)</option>
                                    <option value="dropdown">Dropdown Select</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="q.question_type === 'radio'" class="inline-flex items-center gap-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 px-2.5 py-1 text-[9px] uppercase tracking-wider text-blue-400 font-semibold">
                            🔘 Single select — consumer picks exactly ONE option
                        </div>
                        <div x-show="q.question_type === 'checkbox'" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 text-[9px] uppercase tracking-wider text-emerald-400 font-semibold">
                            ☑️ Multi select — consumer can pick MULTIPLE options
                        </div>

                        <div x-show="['radio', 'checkbox', 'dropdown'].includes(q.question_type)" class="border-l-2 border-brand-red/30 pl-4 space-y-2">
                            <label class="block text-[10px] uppercase tracking-[0.2em] text-brand-ash font-medium mb-1">Answer Options</label>
                            <div class="space-y-2">
                                <template x-for="(opt, oIndex) in q.options" :key="oIndex">
                                    <div class="flex items-center gap-2 max-w-md">
                                        <span class="text-brand-white/30 text-[10px]" x-text="(oIndex + 1) + '.'"></span>
                                        <input type="text" :name="'questions[' + qIndex + '][options][' + oIndex + ']'" x-model="q.options[oIndex]" required
                                            class="flex-1 rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-1 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        <button type="button" @click="removeOption(qIndex, oIndex)" class="text-brand-white/40 hover:text-brand-red text-xs">✕</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="addOption(qIndex)" class="mt-2 text-[10px] uppercase tracking-wider text-emerald-400 hover:text-emerald-300 font-semibold transition">
                                + Add Option
                            </button>
                        </div>

                        <div class="flex items-center justify-between border-t border-brand-white/5 pt-3">
                            <span class="text-brand-white/40 text-[10px] uppercase tracking-[0.1em]" x-text="'Question #' + (qIndex + 1)"></span>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" :name="'questions[' + qIndex + '][is_required]'" value="1" x-model="q.is_required" class="sr-only peer">
                                <div class="relative w-9 h-5 bg-brand-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-brand-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                <span class="ms-2 text-[10px] font-semibold uppercase tracking-wider text-brand-white/70">Required</span>
                            </label>
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="questions.length === 0" class="py-6 text-center text-xs text-brand-white/30 italic">No questions yet. Click "+ Add Question" above.</div>
        </div>

        {{-- ── FORM ACTIONS ─────────────────────────────────────────────── --}}
        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('portal.surveys.show', $survey) }}" class="rounded-xl border border-brand-white/20 hover:bg-brand-white/5 px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition">
                Cancel
            </a>
            <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-brand-white transition shadow-lg">
                Save Changes
            </button>
        </div>
    </form>

@push('scripts')
<script>
function initMapsAutocomplete() {
    const input = document.getElementById('location_url');
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
        const labelInput = document.getElementById('location_label');
        if (labelInput && !labelInput.value && place.name) labelInput.value = place.name;
    });
}

function previewLogo(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        preview.querySelector('img').src = e.target.result;
        preview.querySelector('.logo-filename').textContent = input.files[0].name;
        preview.classList.remove('hidden');
        preview.classList.add('flex');
    };
    reader.readAsDataURL(input.files[0]);
}

function clearLogo(inputId, previewId) {
    document.getElementById(inputId).value = '';
    const preview = document.getElementById(previewId);
    preview.querySelector('img').src = '';
    preview.querySelector('.logo-filename').textContent = '';
    preview.classList.add('hidden');
    preview.classList.remove('flex');
}

function toggleRemove(existingId, uploadAreaId, checked) {
    const existing = document.getElementById(existingId);
    const upload   = document.getElementById(uploadAreaId);
    if (!existing || !upload) return;
    if (checked) {
        existing.classList.add('opacity-40');
        upload.style.pointerEvents = 'none';
        upload.classList.add('opacity-30');
    } else {
        existing.classList.remove('opacity-40');
        upload.style.pointerEvents = '';
        upload.classList.remove('opacity-30');
    }
}

window.surveyExistingQuestions = {!! json_encode($survey->questions->map(fn($q) => [
    'id'            => $q->id,
    'question_text' => $q->question_text,
    'question_type' => $q->question_type,
    'options'       => $q->options ?? ['Option 1'],
    'is_required'   => (bool) $q->is_required,
])->values()->toArray()) !!};

function surveyEditBuilder() {
    console.log('surveyEditBuilder function called');
    return {
        questions: window.surveyExistingQuestions || [],
        init() {
            console.log('surveyEditBuilder init called. Questions:', this.questions);
            if (this.questions.length === 0) this.addQuestion();
        },
        addQuestion() {
            this.questions.push({ id: null, question_text: '', question_type: 'short_text', options: ['Option 1'], is_required: false });
        },
        removeQuestion(index) {
            this.questions.splice(index, 1);
        },
        addOption(qIndex) {
            this.questions[qIndex].options.push('Option ' + (this.questions[qIndex].options.length + 1));
        },
        removeOption(qIndex, oIndex) {
            this.questions[qIndex].options.splice(oIndex, 1);
            if (this.questions[qIndex].options.length === 0) this.questions[qIndex].options.push('Option 1');
        },
        moveUp(index) {
            if (index > 0) {
                [this.questions[index - 1], this.questions[index]] = [this.questions[index], this.questions[index - 1]];
            }
        },
        moveDown(index) {
            if (index < this.questions.length - 1) {
                [this.questions[index], this.questions[index + 1]] = [this.questions[index + 1], this.questions[index]];
            }
        }
    };
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places&callback=initMapsAutocomplete" async defer></script>
@endpush
</x-app-layout>
