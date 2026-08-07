<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfect Store Native Audit - CMIH Africa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-[#0b0c10] text-brand-white font-sans antialiased">
    <header class="sticky top-0 z-40 border-b border-brand-white/10 bg-brand-black/85 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <a href="{{ $outlet ? route('merchandisers.visit', $outlet) : route('merchandisers.dashboard') }}" class="text-xs font-bold text-brand-white/60 transition hover:text-brand-white">
                Back
            </a>
            <div class="text-right">
                <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Native Inbuilt Form</p>
                <h1 class="font-display text-2xl tracking-wider text-brand-white">Perfect Store Audit</h1>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if ($errors->any())
            <div class="mb-5 rounded-2xl border border-brand-red/30 bg-brand-red/10 p-4 text-sm text-red-100">
                <p class="font-bold">Please complete the highlighted required fields.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('status'))
            <div class="mb-5 rounded-2xl border border-green-500/30 bg-green-500/10 p-4 text-sm font-semibold text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <section class="mb-6 grid gap-4 lg:grid-cols-[1.4fr_0.6fr]">
            <div class="rounded-2xl border border-brand-white/10 bg-brand-black/45 p-5">
                <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">{{ $schema['version'] ?? 'Perfect Store 2.0' }}</p>
                <h2 class="mt-2 text-xl font-bold text-brand-white">{{ $form->title }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-brand-white/55">
                    This is the inbuilt mirror of the live Google Form. It stores the response directly in the portal database while the Google Form remains available during the transition period.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if($form->google_enabled && $form->google_form_url)
                        <a href="{{ $form->google_form_url }}" target="_blank" rel="noopener" class="rounded-xl border border-sky-400/20 bg-sky-500/10 px-4 py-2 text-xs font-bold uppercase tracking-wider text-sky-100 hover:bg-sky-500/20">
                            Open Google Form
                        </a>
                    @endif
                    <span class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-2 text-xs font-bold uppercase tracking-wider text-emerald-100">
                        {{ $schema['total_questions'] ?? 120 }} Native Questions
                    </span>
                </div>
            </div>
            <div class="rounded-2xl border border-brand-white/10 bg-brand-black/45 p-5">
                <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Current Context</p>
                <dl class="mt-3 space-y-3 text-xs">
                    <div>
                        <dt class="text-brand-white/40">Merchandiser</dt>
                        <dd class="mt-0.5 font-semibold text-brand-white">{{ auth()->user()->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-brand-white/40">Outlet</dt>
                        <dd class="mt-0.5 font-semibold text-brand-white">{{ $outlet?->name ?? 'Select below' }}</dd>
                    </div>
                    @if($existingSubmission)
                        <div>
                            <dt class="text-brand-white/40">Last saved</dt>
                            <dd class="mt-0.5 font-semibold text-emerald-200">{{ $existingSubmission->submitted_at?->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>

        <form method="POST" action="{{ route('merchandisers.native-forms.submit', $form) }}" class="space-y-4"
            x-data="perfectStoreNativeForm({!! \Illuminate\Support\Js::from($outletDefaultMap ?? []) !!}, {!! \Illuminate\Support\Js::from($questionMeta ?? []) !!}, {!! \Illuminate\Support\Js::from($systemDefaultKeys ?? []) !!})"
            x-init="init()">
            @csrf

            @if($outlet)
                <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
            @else
                <div class="rounded-2xl border border-brand-white/10 bg-brand-black/45 p-5">
                    <label for="outlet_id" class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash">Outlet for this audit</label>
                    <select id="outlet_id" name="outlet_id" required @change="applyOutletDefaults($event.target.value)" class="mt-2 w-full rounded-xl border border-brand-white/10 bg-brand-black px-3 py-3 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                        <option value="">Select today's outlet</option>
                        @foreach($todaysOutlets as $optionOutlet)
                            <option value="{{ $optionOutlet->id }}" @selected((string) old('outlet_id') === (string) $optionOutlet->id)>{{ $optionOutlet->name }}{{ $optionOutlet->code ? ' - ' . $optionOutlet->code : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="rounded-2xl border border-sky-400/15 bg-sky-500/10 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-sky-200">Smart Assist</p>
                        <p class="mt-1 text-xs text-brand-white/55">Trusted profile, route, and outlet values are auto-filled and protected during save.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wider">
                        <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-emerald-100" x-text="autofilledCount + ' auto-filled'"></span>
                        <span class="rounded-full border border-brand-white/10 bg-brand-black/40 px-3 py-1 text-brand-white/70" x-text="requiredMissing + ' required open'"></span>
                        <span class="rounded-full border px-3 py-1" :class="smartFlags.length ? 'border-amber-400/30 bg-amber-500/10 text-amber-100' : 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'" x-text="smartFlags.length + ' review flags'"></span>
                    </div>
                </div>
                <div x-show="smartFlags.length" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2">
                    <template x-for="flag in smartFlags" :key="flag">
                        <div class="rounded-xl border border-amber-400/20 bg-brand-black/40 px-3 py-2 text-xs text-amber-100" x-text="flag"></div>
                    </template>
                </div>
            </div>

            @foreach($sections as $sectionIndex => $section)
                <details class="rounded-2xl border border-brand-white/10 bg-brand-black/45" {{ $sectionIndex < 4 ? 'open' : '' }}>
                    <summary class="cursor-pointer px-5 py-4">
                        <span class="block text-[10px] uppercase tracking-[0.25em] text-brand-ash">Section {{ $sectionIndex + 1 }} / {{ count($sections) }}</span>
                        <span class="mt-1 block text-lg font-bold text-brand-white">{{ $section['title'] }}</span>
                        @if(!empty($section['description']))
                            <span class="mt-1 block text-xs leading-relaxed text-brand-white/45">{{ $section['description'] }}</span>
                        @endif
                    </summary>

                    <div class="border-t border-brand-white/10 p-5">
                        @if(in_array($section['kind'], ['quantity_loop', 'facing_loop', 'share_loop'], true))
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px] text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-brand-white/10 text-[10px] uppercase tracking-wider text-brand-ash">
                                            <th class="pb-3">Item</th>
                                            <th class="w-40 pb-3">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-brand-white/5">
                                        @foreach($section['questions'] as $question)
                                            @php
                                                $key = $question['key'];
                                                $value = old('answers.' . $key, $defaults[$key] ?? '');
                                            @endphp
                                            <tr>
                                                <td class="py-3 pr-4 font-semibold text-brand-white/80">{{ $question['label'] }}</td>
                                                <td class="py-3">
                                                    <input type="number" name="answers[{{ $key }}]" value="{{ $value }}" min="{{ $question['min'] ?? 0 }}" step="{{ $question['step'] ?? 1 }}" inputmode="numeric" autocomplete="off" data-answer-key="{{ $key }}" data-answer-metric="{{ $question['metric'] ?? '' }}" data-answer-label="{{ $question['label'] }}" data-answer-section="{{ $section['key'] ?? '' }}" @if($question['required']) required data-required-answer="1" @endif class="w-full rounded-lg border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-brand-white focus:border-brand-red focus:ring-0">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif($section['kind'] === 'planogram_loop')
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-brand-white/10 text-[10px] uppercase tracking-wider text-brand-ash">
                                            <th class="pb-3">SKU</th>
                                            <th class="w-[360px] pb-3">Planogram status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-brand-white/5">
                                        @foreach($section['questions'] as $question)
                                            @php
                                                $key = $question['key'];
                                                $value = old('answers.' . $key, $defaults[$key] ?? '');
                                            @endphp
                                            <tr>
                                                <td class="py-3 pr-4 font-semibold text-brand-white/80">{{ $question['label'] }}</td>
                                                <td class="py-3">
                                                    <div class="grid grid-cols-3 gap-2">
                                                        @foreach($question['options'] as $option)
                                                            <label class="flex items-center justify-center gap-2 rounded-lg border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-[11px] font-bold text-brand-white/70">
                                                                <input type="radio" name="answers[{{ $key }}]" value="{{ $option }}" @checked((string) $value === (string) $option) data-answer-key="{{ $key }}" data-answer-metric="{{ $question['metric'] ?? '' }}" data-answer-label="{{ $question['label'] }}" data-answer-section="{{ $section['key'] ?? '' }}" @if($question['required']) required data-required-answer="1" @endif class="border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                                                {{ $option }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="grid gap-4 md:grid-cols-2">
                                @foreach($section['questions'] as $question)
                                    @php
                                        $key = $question['key'];
                                        $value = old('answers.' . $key, $defaults[$key] ?? '');
                                        $options = $template->optionsFor($question, $value);
                                        $isLocked = in_array($key, $systemDefaultKeys ?? [], true) && filled($value);
                                        $autocomplete = in_array($question['label'], ['DSR', 'Merchandiser Name'], true) ? 'name' : 'off';
                                    @endphp
                                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-4">
                                        <label class="flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-wider text-brand-white/70" for="{{ $key }}">
                                            <span>{{ $question['label'] }}{{ $question['required'] ? ' *' : '' }}</span>
                                            @if($isLocked)
                                                <span class="rounded-full border border-sky-400/20 bg-sky-500/10 px-2 py-0.5 text-[9px] text-sky-100">Auto-filled</span>
                                            @endif
                                        </label>

                                        @if($question['type'] === 'select')
                                            @if($isLocked)
                                                <input type="hidden" name="answers[{{ $key }}]" value="{{ $value }}" data-answer-key="{{ $key }}" data-answer-metric="{{ $question['metric'] ?? '' }}" data-answer-label="{{ $question['label'] }}" data-answer-section="{{ $section['key'] ?? '' }}" data-locked-answer="1">
                                            @endif
                                            <select id="{{ $key }}" name="answers[{{ $key }}]" @disabled($isLocked) data-answer-key="{{ $key }}" data-answer-metric="{{ $question['metric'] ?? '' }}" data-answer-label="{{ $question['label'] }}" data-answer-section="{{ $section['key'] ?? '' }}" @if($question['required']) required data-required-answer="1" @endif class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0 disabled:border-sky-400/20 disabled:text-sky-100">
                                                <option value="">Select</option>
                                                @foreach($options as $option)
                                                    <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($question['type'] === 'radio')
                                            @if($isLocked)
                                                <input type="hidden" name="answers[{{ $key }}]" value="{{ $value }}" data-answer-key="{{ $key }}" data-answer-metric="{{ $question['metric'] ?? '' }}" data-answer-label="{{ $question['label'] }}" data-answer-section="{{ $section['key'] ?? '' }}" data-locked-answer="1">
                                            @endif
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach($options as $option)
                                                    <label class="inline-flex items-center gap-2 rounded-lg border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-xs text-brand-white/75">
                                                        <input type="radio" name="answers[{{ $key }}]" value="{{ $option }}" @checked((string) $value === (string) $option) @disabled($isLocked) data-answer-key="{{ $key }}" data-answer-metric="{{ $question['metric'] ?? '' }}" data-answer-label="{{ $question['label'] }}" data-answer-section="{{ $section['key'] ?? '' }}" @if($question['required']) required data-required-answer="1" @endif class="border-brand-white/20 bg-brand-black text-brand-red focus:ring-brand-red disabled:border-sky-400/30">
                                                        {{ $option }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @else
                                            <input id="{{ $key }}" type="text" name="answers[{{ $key }}]" value="{{ $value }}" autocomplete="{{ $autocomplete }}" data-answer-key="{{ $key }}" data-answer-metric="{{ $question['metric'] ?? '' }}" data-answer-label="{{ $question['label'] }}" data-answer-section="{{ $section['key'] ?? '' }}" @if($isLocked) readonly data-locked-answer="1" @endif @if($question['required']) required data-required-answer="1" @endif class="mt-2 w-full rounded-lg border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30 focus:border-brand-red focus:ring-0 read-only:border-sky-400/20 read-only:text-sky-100">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </details>
            @endforeach

            <div class="sticky bottom-0 z-30 -mx-4 border-t border-brand-white/10 bg-brand-black/90 px-4 py-4 backdrop-blur-xl">
                <div class="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-brand-white/50">Submitting here saves the inbuilt response to the CMIH portal database.</p>
                    <button type="submit" class="rounded-xl bg-brand-red px-6 py-3 text-xs font-bold uppercase tracking-[0.2em] text-white shadow-lg shadow-brand-red/20 transition hover:bg-red-700">
                        Save Inbuilt Audit
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        function perfectStoreNativeForm(outletDefaults, questionMeta, systemDefaultKeys) {
            return {
                outletDefaults: outletDefaults || {},
                questionMeta: questionMeta || [],
                systemDefaultKeys: systemDefaultKeys || [],
                autofilledCount: 0,
                requiredMissing: 0,
                smartFlags: [],

                init() {
                    var outletSelect = this.$root.querySelector('#outlet_id');
                    this.applyOutletDefaults(outletSelect ? outletSelect.value : null, false);
                    this.audit();
                    this.$root.addEventListener('input', this.audit.bind(this));
                    this.$root.addEventListener('change', this.audit.bind(this));
                },

                selectorFor(key) {
                    return '[data-answer-key="' + key + '"]';
                },

                applyOutletDefaults(outletId, shouldAudit) {
                    var defaults = this.outletDefaults[outletId] || {};

                    Object.keys(defaults).forEach((key) => {
                        var value = defaults[key] == null ? '' : String(defaults[key]);
                        this.$root.querySelectorAll(this.selectorFor(key)).forEach((field) => {
                            if (field.type === 'radio') {
                                field.checked = String(field.value) === value;
                                return;
                            }

                            field.value = value;
                        });
                    });

                    var lockedKeys = new Set(this.systemDefaultKeys.concat(Object.keys(defaults)));
                    this.autofilledCount = lockedKeys.size;

                    if (shouldAudit !== false) {
                        this.audit();
                    }
                },

                valueFor(key) {
                    var fields = Array.from(this.$root.querySelectorAll(this.selectorFor(key)));
                    var checked = fields.find((field) => field.type === 'radio' && field.checked);

                    if (checked) {
                        return String(checked.value || '').trim();
                    }

                    var field = fields.find((candidate) => candidate.type !== 'radio');

                    return field ? String(field.value || '').trim() : '';
                },

                numericValueFor(key) {
                    var value = this.valueFor(key);
                    var number = Number(value);

                    return value !== '' && Number.isFinite(number) ? number : null;
                },

                normalizedLabel(label) {
                    return String(label || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
                },

                audit() {
                    this.requiredMissing = this.questionMeta.filter((question) => {
                        return question.required && this.valueFor(question.key) === '';
                    }).length;

                    var flags = [];
                    var numericQuestions = this.questionMeta.filter((question) => {
                        return ['quantity_on_shelf', 'facings', 'share_of_shelf_total'].includes(question.metric);
                    });
                    var numericValues = numericQuestions
                        .map((question) => this.numericValueFor(question.key))
                        .filter((value) => value !== null);

                    if (numericValues.length >= 20) {
                        var zeroCount = numericValues.filter((value) => value === 0).length;

                        if (zeroCount / numericValues.length >= 0.8) {
                            flags.push('Most numeric entries are zero. Review the shelf before saving.');
                        }
                    }

                    var planogramQuestions = this.questionMeta.filter((question) => question.metric === 'planogram_status');
                    var planogramValues = planogramQuestions
                        .map((question) => this.valueFor(question.key))
                        .filter((value) => value !== '');

                    if (planogramValues.length >= 10 && new Set(planogramValues).size === 1) {
                        flags.push('Planogram statuses repeat across all checked SKUs. Confirm this is correct.');
                    }

                    var positiveStockBySku = new Set();
                    numericQuestions
                        .filter((question) => question.metric === 'quantity_on_shelf' || question.metric === 'facings')
                        .forEach((question) => {
                            var value = this.numericValueFor(question.key);
                            if (value !== null && value > 0) {
                                positiveStockBySku.add(this.normalizedLabel(question.label));
                            }
                        });

                    var oosWithStock = planogramQuestions.some((question) => {
                        return this.valueFor(question.key) === 'OOS' && positiveStockBySku.has(this.normalizedLabel(question.label));
                    });

                    if (oosWithStock) {
                        flags.push('An item marked OOS also has shelf stock or facings recorded.');
                    }

                    this.smartFlags = flags.slice(0, 3);
                },
            };
        }
    </script>
</body>
</html>
