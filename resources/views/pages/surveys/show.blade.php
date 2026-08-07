@extends('layouts.site')

@section('title', $survey->title . ' - CMIH Africa')
@section('description', $survey->description ?? 'Please fill out this survey to attend our activation/event.')

@section('content')
    <section class="section-padding relative overflow-hidden bg-brand-black min-h-[80vh] flex items-center">
        {{-- Aurora Background --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-20 -right-20 w-[600px] h-[600px] bg-brand-red/40 rounded-full blur-[130px] aurora-blend"></div>
            <div class="absolute -bottom-20 -left-20 w-[500px] h-[500px] bg-brand-red-dark/50 rounded-full blur-[120px] aurora-blend opacity-80"></div>
            <div class="absolute inset-0 opacity-20 brightness-100 contrast-150 mix-blend-overlay" style="background-image: url('{{ asset('images/noise.svg') }}')"></div>
        </div>

        <div class="relative z-10 mx-auto max-w-2xl px-6 w-full py-10">
            <div class="glass-panel rounded-3xl p-8 border border-brand-white/10 bg-brand-black/60 shadow-2xl space-y-6">

                {{-- ── DUAL LOGO HEADER ──────────────────────────────── --}}
                @if($survey->cmih_logo_path || $survey->client_logo_path || $survey->client_logo_path_2)
                    <div class="flex items-center justify-between gap-4 pb-5 border-b border-brand-white/10">

                        {{-- CMIH Logo (left) --}}
                        <div class="flex flex-col items-start gap-1 shrink-0">
                            @if($survey->cmih_logo_path)
                                <img src="{{ Storage::url($survey->cmih_logo_path) }}" alt="CMIH" class="h-12 w-auto object-contain max-w-[110px]">
                            @else
                                <div class="h-12"></div>
                            @endif
                        </div>

                        <div class="flex-1 border-t border-dashed border-brand-white/10"></div>

                        {{-- Client Logos (right side) --}}
                        <div class="flex items-center gap-5 shrink-0">
                            @if($survey->client_logo_path)
                                <div class="flex flex-col items-center gap-1">
                                    <img src="{{ Storage::url($survey->client_logo_path) }}" alt="{{ $survey->client_brand_name ?? 'Partner' }}" class="h-12 w-auto object-contain max-w-[100px]">
                                    @if($survey->client_brand_name)
                                        <span class="text-[8px] uppercase tracking-[0.2em] text-brand-white/40">{{ $survey->client_brand_name }}</span>
                                    @endif
                                </div>
                            @endif
                            @if($survey->client_logo_path_2)
                                <div class="flex flex-col items-center gap-1">
                                    <img src="{{ Storage::url($survey->client_logo_path_2) }}" alt="{{ $survey->client_brand_name_2 ?? 'Partner 2' }}" class="h-12 w-auto object-contain max-w-[100px]">
                                    @if($survey->client_brand_name_2)
                                        <span class="text-[8px] uppercase tracking-[0.2em] text-brand-white/40">{{ $survey->client_brand_name_2 }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                    </div>
                @endif

                {{-- ── SURVEY TITLE / DESCRIPTION ───────────────────── --}}
                <div class="text-center space-y-3">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Consumer Survey</p>
                    <h1 class="text-3xl font-display text-brand-white leading-tight">{{ $survey->title }}</h1>
                    @if($survey->description)
                        <div class="text-sm text-brand-white/70 font-sans max-w-lg mx-auto">
                            {!! nl2br(e($survey->description)) !!}
                        </div>
                    @endif
                </div>

                {{-- ── STATUS CHECKS ─────────────────────────────────── --}}
                @if($survey->status === 'closed')
                    <div class="rounded-2xl border border-brand-red/30 bg-brand-red/10 p-5 text-center text-sm text-brand-red font-semibold">
                        🔒 This survey is now closed. Thank you for your interest!
                    </div>
                @else
                    {{-- ── SURVEY FORM ───────────────────────────────── --}}
                    <form method="POST" action="{{ route('surveys.submit', $survey->slug) }}" class="space-y-6">
                        @csrf

                        @if ($errors->any())
                            <div class="rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-xs text-red-200">
                                <ul class="list-disc pl-5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Demographics Block (only if NOT anonymous) --}}
                        @if(!$survey->is_anonymous)
                            <div class="border-b border-brand-white/10 pb-6 space-y-4">
                                <h3 class="text-xs uppercase tracking-[0.25em] text-brand-ash font-semibold mb-2">👤 Your Details</h3>

                                <div>
                                    <label for="name" class="block text-xs text-brand-white/70 mb-1">Full Name <span class="text-brand-red">*</span></label>
                                    <input type="text" id="name" name="name" required
                                        class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20"
                                        placeholder="John Doe" value="{{ old('name') }}">
                                    @error('name')<span class="text-xs text-brand-red mt-1 block">{{ $message }}</span>@enderror
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="email" class="block text-xs text-brand-white/70 mb-1">Email Address <span class="text-brand-red">*</span></label>
                                        <input type="email" id="email" name="email" required
                                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20"
                                            placeholder="john@example.com" value="{{ old('email') }}">
                                        @error('email')<span class="text-xs text-brand-red mt-1 block">{{ $message }}</span>@enderror
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-xs text-brand-white/70 mb-1">Phone Number <span class="text-brand-red">*</span></label>
                                        <input type="tel" id="phone" name="phone" required
                                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20"
                                            placeholder="+233 XX XXX XXXX" value="{{ old('phone') }}">
                                        @error('phone')<span class="text-xs text-brand-red mt-1 block">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="age" class="block text-xs text-brand-white/70 mb-1">Age (Years)</label>
                                        <input type="number" id="age" name="age" min="1" max="120"
                                            class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/20"
                                            placeholder="e.g. 25" value="{{ old('age') }}">
                                        @error('age')<span class="text-xs text-brand-red mt-1 block">{{ $message }}</span>@enderror
                                    </div>
                                    <div>
                                        <label for="gender" class="block text-xs text-brand-white/70 mb-1">Gender</label>
                                        <select id="gender" name="gender" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                            <option value="">-- Select --</option>
                                            <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                                            <option value="Female" @selected(old('gender') === 'Female')>Female</option>
                                            <option value="Other" @selected(old('gender') === 'Other')>Other</option>
                                            <option value="Prefer not to say" @selected(old('gender') === 'Prefer not to say')>Prefer not to say</option>
                                        </select>
                                        @error('gender')<span class="text-xs text-brand-red mt-1 block">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ── CUSTOM QUESTIONS ─────────────────────── --}}
                        @if(count($survey->questions) > 0)
                            <div class="space-y-6">
                                <h3 class="text-xs uppercase tracking-[0.25em] text-brand-ash font-semibold">📋 Survey Questions</h3>

                                @foreach($survey->questions as $q)
                                    <div class="space-y-2">
                                        <label class="block text-sm text-brand-white font-medium">
                                            {{ $q->question_text }}
                                            @if($q->is_required)<span class="text-brand-red">*</span>@endif
                                        </label>

                                        {{-- Short Answer --}}
                                        @if($q->question_type === 'short_text')
                                            <input type="text" name="answers[{{ $q->id }}]"
                                                @if($q->is_required) required @endif
                                                class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/15"
                                                placeholder="Type your response..."
                                                value="{{ old('answers.'.$q->id) }}">
                                        @endif

                                        {{-- Paragraph --}}
                                        @if($q->question_type === 'paragraph')
                                            <textarea name="answers[{{ $q->id }}]"
                                                @if($q->is_required) required @endif
                                                rows="3"
                                                class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0 placeholder-brand-white/15"
                                                placeholder="Type your detailed response...">{{ old('answers.'.$q->id) }}</textarea>
                                        @endif

                                        {{-- Dropdown --}}
                                        @if($q->question_type === 'dropdown')
                                            <select name="answers[{{ $q->id }}]"
                                                @if($q->is_required) required @endif
                                                class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-4 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-0">
                                                <option value="">-- Choose an option --</option>
                                                @foreach($q->options ?? [] as $opt)
                                                    <option value="{{ $opt }}" @selected(old('answers.'.$q->id) == $opt)>{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                        @endif

                                        {{-- Radio — SINGLE SELECT ONLY --}}
                                        @if($q->question_type === 'radio')
                                            <p class="text-[10px] text-brand-white/30 uppercase tracking-wider">Select one option</p>
                                            <div class="space-y-2 mt-1">
                                                @foreach($q->options ?? [] as $opt)
                                                    <label class="flex items-center gap-3 cursor-pointer group">
                                                        <span class="relative flex items-center justify-center shrink-0">
                                                            <input type="radio"
                                                                   name="answers[{{ $q->id }}]"
                                                                   value="{{ $opt }}"
                                                                   @if($q->is_required) required @endif
                                                                   @checked(old('answers.'.$q->id) == $opt)
                                                                   class="sr-only peer">
                                                            {{-- Custom styled radio circle --}}
                                                            <span class="h-5 w-5 rounded-full border-2 border-brand-white/25 bg-transparent peer-checked:border-brand-red transition group-hover:border-brand-white/50 flex items-center justify-center">
                                                                <span class="h-2.5 w-2.5 rounded-full bg-brand-red scale-0 peer-checked:scale-100 transition-transform duration-150"></span>
                                                            </span>
                                                        </span>
                                                        <span class="text-sm text-brand-white/80 group-hover:text-brand-white transition">{{ $opt }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Checkbox — MULTI SELECT --}}
                                        @if($q->question_type === 'checkbox')
                                            <p class="text-[10px] text-brand-white/30 uppercase tracking-wider">Select all that apply</p>
                                            <div class="space-y-2 mt-1">
                                                @foreach($q->options ?? [] as $opt)
                                                    <label class="flex items-center gap-3 cursor-pointer group">
                                                        <input type="checkbox"
                                                               name="answers[{{ $q->id }}][]"
                                                               value="{{ $opt }}"
                                                               @checked(is_array(old('answers.'.$q->id)) && in_array($opt, old('answers.'.$q->id)))
                                                               class="h-5 w-5 rounded border-brand-white/25 bg-brand-black/40 text-brand-red focus:ring-0 focus:ring-offset-0">
                                                        <span class="text-sm text-brand-white/80 group-hover:text-brand-white transition">{{ $opt }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif

                                        @error('answers.' . $q->id)
                                            <span class="text-xs text-brand-red mt-1 block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Submit --}}
                        <div class="pt-4">
                            <button type="submit"
                                class="w-full rounded-2xl bg-gradient-to-r from-brand-red to-brand-red-dark hover:from-brand-red-dark hover:to-brand-red py-3.5 text-xs uppercase tracking-[0.25em] font-semibold text-brand-white transition shadow-lg">
                                Submit
                            </button>
                        </div>
                    </form>
                @endif

            </div>
        </div>
    </section>
@endsection

@push('styles')
<style>
/* Make the radio inner dot work with the sr-only peer trick */
input[type="radio"]:checked + span > span { transform: scale(1); }
input[type="radio"]:checked + span { border-color: #E50914; }
</style>
@endpush
