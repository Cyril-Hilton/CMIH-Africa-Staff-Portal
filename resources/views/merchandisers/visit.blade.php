<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Store Visit - {{ $outlet->name }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#0b0c10] text-[#c5c6c7] font-sans min-h-screen antialiased selection:bg-amber-500 selection:text-black">

    <header class="border-b border-brand-white/10 bg-brand-black/40 backdrop-blur-xl sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('merchandisers.dashboard') }}" class="text-xs text-brand-white/60 hover:text-brand-white font-bold flex items-center gap-1.5 transition-all">
                ← Return to Dashboard
            </a>
            <span class="text-xs uppercase tracking-[0.2em] font-semibold text-brand-ash">Store Execution Form</span>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-6">
        
        <form method="POST" action="{{ route('merchandisers.visit.store', $outlet) }}" enctype="multipart/form-data" class="space-y-6"
            x-data="skuAiVisitForm('{{ route('merchandisers.visit.ai-detect', $outlet) }}', '{{ old('sku_entry_mode', 'manual') }}')">
            @csrf

            @if ($errors->any())
                <div class="rounded-2xl border border-brand-red/30 bg-brand-red/10 p-4 text-sm text-red-200">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Metadata Box -->
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-white/5 space-y-3">
                <h2 class="text-xs uppercase tracking-[0.2em] text-brand-ash font-bold">Outlet Metadata</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-brand-white/50">Merchandiser Profile</p>
                        <p class="font-bold text-brand-white mt-0.5">{{ auth()->user()->name }}</p>
                    </div>
                    <div>
                        <p class="text-brand-white/50">Assigned KD</p>
                        <p class="font-bold text-brand-white mt-0.5">{{ auth()->user()->merchandiserKd->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-brand-white/50">Target Outlet</p>
                        <p class="font-bold text-amber-500 mt-0.5">{{ $outlet->name }} ({{ $outlet->code }})</p>
                    </div>
                    <div>
                        <p class="text-brand-white/50">Supervisor / Line Manager</p>
                        <p class="font-bold text-brand-white mt-0.5">{{ auth()->user()->supervisor->name ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- POSM Checklist -->
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 space-y-4">
                <h3 class="text-lg font-display text-brand-white tracking-wider">📦 POSM Checklist</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-brand-white/5 bg-brand-white/5 p-4 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-semibold text-brand-white block">Branded Shelf Available</span>
                            <span class="text-xs text-brand-white/40">Is CMIH branded shelf displayed?</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-1.5 text-xs text-brand-white cursor-pointer">
                                <input type="radio" name="branded_shelf_available" value="1" required class="text-amber-500 bg-brand-black border-brand-white/20 focus:ring-0"> Yes
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-brand-white cursor-pointer">
                                <input type="radio" name="branded_shelf_available" value="0" required class="text-amber-500 bg-brand-black border-brand-white/20 focus:ring-0"> No
                            </label>
                        </div>
                    </div>

                    <div class="rounded-xl border border-brand-white/5 bg-brand-white/5 p-4 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-semibold text-brand-white block">Hangers Available</span>
                            <span class="text-xs text-brand-white/40">Are brand hangers active?</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-1.5 text-xs text-brand-white cursor-pointer">
                                <input type="radio" name="hangers_available" value="1" required class="text-amber-500 bg-brand-black border-brand-white/20 focus:ring-0"> Yes
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-brand-white cursor-pointer">
                                <input type="radio" name="hangers_available" value="0" required class="text-amber-500 bg-brand-black border-brand-white/20 focus:ring-0"> No
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            @if($googleForms->isNotEmpty())
                <div class="glass-panel rounded-2xl p-5 border border-sky-400/20 bg-sky-500/10 space-y-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.25em] text-sky-200/80">Outlet Forms</p>
                        <h3 class="mt-1 text-lg font-display text-brand-white tracking-wider">Google Forms & Surveys</h3>
                        <p class="mt-1 text-xs text-brand-white/55">Open the assigned form, submit it, then mark it completed here so supervisors can track progress.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach($googleForms as $form)
                            @php
                                $googleCompleted = in_array($form->id, $googleFormCompletionIds, true);
                                $nativeCompleted = in_array($form->id, $nativeFormCompletionIds, true);
                                $completed = $googleCompleted || $nativeCompleted;
                            @endphp
                            <div class="rounded-xl border border-brand-white/10 bg-brand-black/40 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-white">{{ $form->title }}</p>
                                        @if($form->description)
                                            <p class="mt-1 text-xs text-brand-white/50">{{ $form->description }}</p>
                                        @endif
                                    </div>
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $completed ? 'border-green-500/20 bg-green-500/10 text-green-300' : 'border-amber-500/20 bg-amber-500/10 text-amber-200' }}">{{ $nativeCompleted ? 'Inbuilt Done' : ($googleCompleted ? 'Google Done' : 'Pending') }}</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if($form->google_enabled && $form->google_form_url)
                                        <a href="{{ $form->google_form_url }}" target="_blank" rel="noopener" class="rounded-lg bg-sky-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-sky-400">Use Google Form</a>
                                    @endif
                                    @if($form->native_enabled)
                                        <a href="{{ route('merchandisers.native-forms.show', ['form' => $form, 'outlet_id' => $outlet->id]) }}" class="rounded-lg bg-emerald-500 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-emerald-400">{{ $nativeCompleted ? 'Edit Inbuilt Form' : 'Use Inbuilt Form' }}</a>
                                    @endif
                                    @if($form->google_enabled && ! $googleCompleted)
                                        <button type="submit" form="google-form-complete-{{ $form->id }}" class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">Mark Google Complete</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 space-y-4">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Perfect Store</p>
                    <h3 class="mt-1 text-lg font-display text-brand-white tracking-wider">Planogram Assessment</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Compare the outlet shelf with the applicable Perfect Store reference and record compliance.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-brand-white/60 mb-2">Applicable Planogram</label>
                        <select name="planogram_id" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white focus:border-amber-500 focus:ring-0">
                            <option value="">No specific planogram selected</option>
                            @foreach($planograms as $planogram)
                                <option value="{{ $planogram->id }}">{{ $planogram->title }}{{ $planogram->channel_type ? ' - ' . $planogram->channel_type : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-brand-white/60 mb-2">Compliance Score (%)</label>
                        <input type="number" name="planogram_score" min="0" max="100" placeholder="0 - 100" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-amber-500 focus:ring-0">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-brand-white/60 mb-2">Shelf / Planogram Photo</label>
                        <input type="file" name="planogram_photo" accept="image/*" capture="environment" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white file:mr-3 file:rounded-lg file:border-0 file:bg-amber-500 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-black">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider text-brand-white/60 mb-2">Notes</label>
                        <textarea name="planogram_notes" rows="3" placeholder="What is compliant, missing, blocked, or needs correction?" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-amber-500 focus:ring-0"></textarea>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-2">
                    <div class="rounded-xl border border-amber-400/20 bg-amber-500/10 p-4">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-amber-200/80">Perfect Store Quick Guide</p>
                        <ul class="mt-3 space-y-2 text-xs leading-relaxed text-brand-white/65">
                            @foreach($perfectStoreGuide as $guideItem)
                                <li>{{ $guideItem }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.03] p-4">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-white/50">Saved References</p>
                        <div class="mt-3 space-y-3">
                            @forelse($planograms as $planogram)
                                <div class="rounded-lg border border-brand-white/10 bg-brand-black/35 p-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-semibold text-brand-white">{{ $planogram->title }}</p>
                                            <p class="mt-0.5 text-[10px] text-brand-white/40">{{ $planogram->category ?? 'General' }} / {{ $planogram->channel_type ?? 'Any channel' }}</p>
                                        </div>
                                        @if($planogram->reference_file_path)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($planogram->reference_file_path) }}" target="_blank" rel="noopener" class="rounded-lg border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">Open</a>
                                        @endif
                                    </div>
                                    @if($planogram->checklist)
                                        <ul class="mt-2 space-y-1 text-[11px] text-brand-white/50">
                                            @foreach(array_slice($planogram->checklist, 0, 4) as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-brand-white/45">No uploaded planogram reference is active for this outlet channel yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- SKU Stock Execution Metrics -->
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 space-y-4">
                <h3 class="text-lg font-display text-brand-white tracking-wider">📊 SKU Stock & Execution Metrics</h3>
                <input type="hidden" name="sku_entry_mode" x-model="skuEntryMode">
                <input type="hidden" name="ai_predictions_json" :value="aiDetectionResult ? JSON.stringify(aiDetectionResult) : ''">

                <div class="rounded-2xl border border-brand-white/10 bg-brand-white/[0.03] p-4 space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button"
                            @click="skuEntryMode = 'manual'"
                            :class="skuEntryMode === 'manual' ? 'border-amber-400 bg-amber-500/15 text-amber-300' : 'border-brand-white/10 bg-brand-white/5 text-brand-white/60 hover:text-brand-white'"
                            class="flex-1 rounded-xl border px-4 py-3 text-left transition-all">
                            <span class="block text-xs font-bold uppercase tracking-[0.2em]">Manual Entry</span>
                            <span class="mt-1 block text-[11px] opacity-70">Enter OSA, facing, shelf share, and planogram manually.</span>
                        </button>
                        <button type="button"
                            @click="skuEntryMode = 'ai'"
                            :class="skuEntryMode === 'ai' ? 'border-sky-400 bg-sky-500/15 text-sky-300' : 'border-brand-white/10 bg-brand-white/5 text-brand-white/60 hover:text-brand-white'"
                            class="flex-1 rounded-xl border px-4 py-3 text-left transition-all">
                            <span class="block text-xs font-bold uppercase tracking-[0.2em]">AI Shelf Detection (Pilot)</span>
                            <span class="mt-1 block text-[11px] opacity-70">Upload shelf photo first; use manual fallback if detection is not ready.</span>
                        </button>
                    </div>

                    <div x-show="skuEntryMode === 'ai'" x-transition class="rounded-xl border border-sky-400/20 bg-sky-500/10 p-4 space-y-3">
                        <div>
                            <label for="ai_shelf_photo" class="block text-[10px] uppercase tracking-[0.25em] text-sky-200/80 mb-2">Shelf Photo for AI Detection</label>
                            <input id="ai_shelf_photo" name="ai_shelf_photo" type="file" accept="image/*" capture="environment" :required="skuEntryMode === 'ai'"
                                x-ref="aiShelfPhoto" @change="aiDetectionResult = null; aiDetectionError = null"
                                class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white file:mr-3 file:rounded-lg file:border-0 file:bg-sky-500 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                        </div>
                        <textarea name="ai_detection_notes" rows="2" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-xs text-brand-white placeholder-brand-white/30 focus:border-sky-400 focus:ring-0" placeholder="Optional notes: shelf angle, poor lighting, products hidden behind other items...">{{ old('ai_detection_notes') }}</textarea>
                        <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-200 leading-relaxed">
                            Pilot mode keeps manual fallback active. Until auto-detect is fully trained, confirm or correct the SKU quantities in the table below before submitting.
                        </div>
                        <button type="button" @click="skuEntryMode = 'manual'" class="text-[10px] uppercase tracking-[0.2em] text-sky-200 hover:text-white">
                            Switch back to manual
                        </button>
                        <button type="button" @click="runAiDetection()" :disabled="aiDetecting"
                            class="w-full rounded-xl bg-sky-500 px-4 py-3 text-xs font-bold uppercase tracking-[0.2em] text-white transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!aiDetecting">Run AI Detection & Prefill Table</span>
                            <span x-show="aiDetecting">Analyzing shelf photo…</span>
                        </button>

                        <div x-show="aiDetectionError" x-cloak class="rounded-xl border border-brand-red/30 bg-brand-red/10 px-3 py-2 text-xs text-red-200" x-text="aiDetectionError"></div>

                        <div x-show="aiDetectionResult" x-cloak class="rounded-xl border border-sky-400/20 bg-brand-black/40 p-3 text-xs text-brand-white/80">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-sky-200" x-text="aiDetectionResult?.message || 'AI result received. Review before submitting.'"></span>
                                <span x-show="aiDetectionResult?.average_confidence !== undefined" class="rounded-full border border-sky-400/25 bg-sky-500/10 px-2 py-0.5 text-[10px] text-sky-200">
                                    Avg confidence: <span x-text="Math.round((aiDetectionResult.average_confidence || 0) * 100)"></span>%
                                </span>
                                <span x-show="aiDetectionResult?.review_required" class="rounded-full border border-amber-400/25 bg-amber-500/10 px-2 py-0.5 text-[10px] text-amber-200">
                                    Human review required
                                </span>
                            </div>
                            <template x-if="aiDetectionResult?.detections?.length">
                                <div class="mt-3 grid gap-2">
                                    <template x-for="detection in aiDetectionResult.detections" :key="detection.sku_id">
                                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.03] p-2">
                                            <p class="font-semibold text-brand-white" x-text="detection.sku_name"></p>
                                            <p class="mt-1 text-[11px] text-brand-ash">
                                                Qty <span class="text-brand-white" x-text="detection.quantity"></span>,
                                                Facing <span class="text-brand-white" x-text="detection.facing"></span>,
                                                Shelf <span class="text-brand-white" x-text="detection.share_of_shelf"></span>%,
                                                Confidence <span class="text-brand-white" x-text="Math.round((detection.confidence || 0) * 100)"></span>%
                                            </p>
                                            <p x-show="detection.notes" class="mt-1 text-[10px] text-brand-white/45" x-text="detection.notes"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[700px] border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 text-brand-ash uppercase tracking-wider">
                                <th class="pb-3 w-[220px]">SKU</th>
                                <th class="pb-3 w-[90px]">OSA Qty</th>
                                <th class="pb-3 w-[90px]">NPD Present</th>
                                <th class="pb-3 w-[90px]">Facing Count</th>
                                <th class="pb-3 w-[100px]">Shelf Share (%)</th>
                                <th class="pb-3 w-[90px]">Planogram</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @foreach($skus as $sku)
                                <tr>
                                    <td class="py-3 pr-2">
                                        <span class="font-semibold text-brand-white block">{{ $sku->name }}</span>
                                    </td>
                                    <td class="py-3 pr-2">
                                        <input type="number" name="skus[{{ $sku->id }}][osa_quantity]" data-sku-id="{{ $sku->id }}" data-field="osa_quantity" value="0" min="0" required class="w-full rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 focus:border-amber-500 focus:ring-0 text-brand-white">
                                    </td>
                                    <td class="py-3 pr-2 text-center">
                                        <select name="skus[{{ $sku->id }}][npd_present]" data-sku-id="{{ $sku->id }}" data-field="npd_present" required class="w-full rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 focus:border-amber-500 focus:ring-0 text-brand-white text-xs">
                                            <option value="0">No</option>
                                            <option value="1">Yes</option>
                                        </select>
                                    </td>
                                    <td class="py-3 pr-2">
                                        <input type="number" name="skus[{{ $sku->id }}][facing]" data-sku-id="{{ $sku->id }}" data-field="facing" value="0" min="0" required class="w-full rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 focus:border-amber-500 focus:ring-0 text-brand-white">
                                    </td>
                                    <td class="py-3 pr-2">
                                        <input type="number" name="skus[{{ $sku->id }}][share_of_shelf]" data-sku-id="{{ $sku->id }}" data-field="share_of_shelf" value="0" min="0" max="100" required class="w-full rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 focus:border-amber-500 focus:ring-0 text-brand-white">
                                    </td>
                                    <td class="py-3 text-center">
                                        <select name="skus[{{ $sku->id }}][planogram_compliant]" data-sku-id="{{ $sku->id }}" data-field="planogram_compliant" required class="w-full rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 focus:border-amber-500 focus:ring-0 text-brand-white text-xs">
                                            <option value="0">No</option>
                                            <option value="1">Yes</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- KD Order Generation Form -->
            <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 space-y-4">
                <h3 class="text-lg font-display text-brand-white tracking-wider">🛒 KD Order Placement</h3>
                <p class="text-xs text-brand-white/50">Specify the order quantity (in Cartons/Crates) to be forwarded to Key Distributor <strong>{{ auth()->user()->merchandiserKd->name ?? 'N/A' }}</strong>. Leave empty if no order is required.</p>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[500px] border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 text-brand-ash uppercase tracking-wider">
                                <th class="pb-3">SKU</th>
                                <th class="pb-3 w-[150px]">Order Quantity (Cartons)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5">
                            @foreach($skus as $sku)
                                <tr>
                                    <td class="py-3">
                                        <span class="font-semibold text-brand-white">{{ $sku->name }}</span>
                                    </td>
                                    <td class="py-3">
                                        <input type="number" name="order_items[{{ $sku->id }}]" min="1" placeholder="--" class="w-full rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 focus:border-amber-500 focus:ring-0 text-brand-white">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end">
                <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-amber-500 hover:bg-amber-600 text-black text-sm uppercase tracking-wider font-bold rounded-xl transition-all shadow-lg hover:shadow-amber-500/10">
                    Submit Visit & Order Reports
                </button>
            </div>

        </form>

        @foreach($googleForms as $form)
            <form id="google-form-complete-{{ $form->id }}" method="POST" action="{{ route('merchandisers.google-forms.complete', $form) }}" class="hidden">
                @csrf
                <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
            </form>
        @endforeach

    </main>

    <script>
        function skuAiVisitForm(aiDetectUrl, initialMode) {
            return {
                skuEntryMode: initialMode || 'manual',
                aiDetecting: false,
                aiDetectionResult: null,
                aiDetectionError: null,

                async runAiDetection() {
                    this.aiDetectionError = null;
                    this.aiDetectionResult = null;

                    const file = this.$refs.aiShelfPhoto?.files?.[0];
                    if (!file) {
                        this.aiDetectionError = 'Please take or upload a shelf photo first.';
                        return;
                    }

                    const formData = new FormData();
                    formData.append('ai_shelf_photo', file);

                    this.aiDetecting = true;
                    try {
                        const response = await fetch(aiDetectUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: formData,
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            this.aiDetectionError = data.message || 'AI detection failed. Please continue manually.';
                            return;
                        }

                        if (response.status === 202 || ['queued', 'processing'].includes(data.job_status)) {
                            this.aiDetectionResult = data;
                            await this.pollAiDetection(data.poll_url);
                            return;
                        }

                        this.aiDetectionResult = data;
                        this.applyAiDetections(data.detections || []);

                        if (data.status === 'not_configured') {
                            this.aiDetectionError = data.message;
                        }
                    } catch (error) {
                        this.aiDetectionError = 'AI detection could not run. Please continue manually.';
                    } finally {
                        this.aiDetecting = false;
                    }
                },

                async pollAiDetection(pollUrl) {
                    if (!pollUrl) {
                        this.aiDetectionError = 'AI detection started, but no result link was returned. Please continue manually.';
                        return;
                    }

                    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

                    for (let attempt = 0; attempt < 40; attempt++) {
                        await wait(attempt < 8 ? 1500 : 2500);

                        const response = await fetch(pollUrl, {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await response.json();
                        this.aiDetectionResult = data;

                        if (['queued', 'processing'].includes(data.job_status)) {
                            continue;
                        }

                        if (data.job_status === 'completed') {
                            this.applyAiDetections(data.detections || []);

                            if (data.status === 'not_configured') {
                                this.aiDetectionError = data.message;
                            }

                            return;
                        }

                        this.aiDetectionError = data.message || 'AI detection could not complete. Please continue manually.';
                        return;
                    }

                    this.aiDetectionError = 'AI detection is taking longer than expected. Please continue manually; results may still finish in the background.';
                },

                applyAiDetections(detections) {
                    detections.forEach((detection) => {
                        this.setSkuField(detection.sku_id, 'osa_quantity', detection.quantity ?? 0);
                        this.setSkuField(detection.sku_id, 'facing', detection.facing ?? 0);
                        this.setSkuField(detection.sku_id, 'share_of_shelf', detection.share_of_shelf ?? 0);
                        this.setSkuField(detection.sku_id, 'npd_present', (detection.quantity ?? 0) > 0 ? 1 : 0);
                        this.setSkuField(detection.sku_id, 'planogram_compliant', detection.planogram_compliant ? 1 : 0);
                    });
                },

                setSkuField(skuId, field, value) {
                    const input = this.$el.querySelector(`[data-sku-id="${skuId}"][data-field="${field}"]`);
                    if (!input) return;
                    input.value = value;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
            };
        }
    </script>

</body>
</html>
