@extends('layouts.site')

@section('title', 'Asset Verification: ' . ($asset->asset_tag_or_code) . ' - CMIH Africa')
@section('description', 'Verified CMIH Africa Office Asset Identification and Assignment Passport.')

@section('content')
<section class="relative min-h-[85vh] py-10 sm:py-16 bg-brand-black overflow-hidden">
    {{-- Aurora glow backdrop --}}
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute -top-24 right-1/4 w-[600px] h-[600px] bg-brand-red/20 rounded-full blur-[140px]"></div>
        <div class="absolute bottom-0 left-1/4 w-[500px] h-[500px] bg-amber-500/10 rounded-full blur-[130px]"></div>
        <div class="absolute inset-0 opacity-20 brightness-100 contrast-150 mix-blend-overlay" style="background-image: url('{{ asset('images/noise.svg') }}')"></div>
    </div>

    <div class="relative mx-auto max-w-3xl px-4 sm:px-6">
        {{-- Top Verification Badge --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 shadow-lg shadow-emerald-500/10">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.25em] text-emerald-400">Official Asset Tag</span>
                        <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-semibold text-emerald-300">Verified</span>
                    </div>
                    <h1 class="text-xs font-mono uppercase tracking-wider text-white/50">Tag: {{ $asset->asset_tag_or_code }}</h1>
                </div>
            </div>

            @auth
                <a href="{{ route('portal.assets.show', $asset) }}" class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-semibold text-white transition hover:bg-white/20">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    Open in Staff Portal
                </a>
            @endauth
        </div>

        {{-- Main Asset Passport Card --}}
        <div class="glass-panel overflow-hidden rounded-3xl border border-white/15 bg-[#0d0d0d]/90 shadow-2xl backdrop-blur-xl">
            {{-- Header Banner --}}
            <div class="border-b border-white/10 bg-gradient-to-r from-brand-red/20 via-black to-brand-red/10 px-6 py-5 sm:px-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-brand-red">CMIH Africa • Physical Asset Passport</p>
                        <h2 class="mt-1 text-2xl sm:text-3xl font-display font-bold text-white tracking-wide">{{ $asset->name }}</h2>
                    </div>
                    <div class="self-start sm:self-auto">
                        <span class="inline-block rounded-xl border border-white/20 bg-black/60 px-3.5 py-1.5 font-mono text-sm font-bold text-amber-300 shadow-inner">
                            {{ $asset->asset_tag_or_code }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8 space-y-8">
                {{-- Asset Image Preview --}}
                <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                    @if($asset->image_path)
                        <img src="{{ Storage::disk('public')->url($asset->image_path) }}" alt="{{ $asset->name }}" class="h-64 sm:h-80 w-full object-cover">
                    @else
                        <div class="flex h-56 sm:h-64 w-full flex-col items-center justify-center gap-2 bg-gradient-to-b from-white/[0.04] to-white/[0.01] text-white/30">
                            <svg class="h-16 w-16 stroke-[1.2]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <p class="text-xs uppercase tracking-widest font-semibold">No Image Uploaded</p>
                            <p class="text-[11px] text-white/40">Verified via barcode/tag: {{ $asset->asset_tag_or_code }}</p>
                        </div>
                    @endif

                    <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between rounded-xl bg-black/75 px-4 py-2 backdrop-blur-md border border-white/10 text-xs">
                        <span class="text-white/60">Condition:</span>
                        <span class="font-bold uppercase tracking-wider {{ in_array(strtolower($asset->condition), ['new', 'good', 'excellent']) ? 'text-emerald-400' : 'text-amber-400' }}">
                            {{ $asset->condition }}
                        </span>
                    </div>
                </div>

                {{-- Key Specifications Table / Grid --}}
                <div>
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-[0.25em] text-white/50">Asset Specifications & Ownership</h3>
                    
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {{-- Category --}}
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Category</p>
                            <p class="mt-1 text-base font-semibold text-white">{{ $asset->category ?: ($asset->type ?: 'Office Asset') }}</p>
                        </div>

                        {{-- Type --}}
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Asset Type</p>
                            <p class="mt-1 text-base font-semibold text-white">{{ $asset->type }}</p>
                        </div>

                        {{-- Status --}}
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Status</p>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full {{ strtolower((string)$asset->status) === 'available' ? 'bg-emerald-400 animate-pulse' : (strtolower((string)$asset->status) === 'in use' ? 'bg-blue-400' : 'bg-amber-400') }}"></span>
                                <p class="text-base font-semibold text-white">{{ $asset->status }}</p>
                            </div>
                        </div>

                        {{-- Condition --}}
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Physical Condition</p>
                            <p class="mt-1 text-base font-semibold text-white">{{ $asset->condition }}</p>
                        </div>

                        {{-- Assigned To + Department (Highlight Box) --}}
                        <div class="rounded-2xl border border-brand-red/30 bg-brand-red/10 p-4 sm:col-span-2 transition hover:bg-brand-red/15">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-brand-red">Assigned Staff & Department</p>
                            <div class="mt-1 flex flex-wrap items-baseline gap-2">
                                <p class="text-lg font-bold text-white">
                                    {{ $asset->assignee?->name ?? 'Unassigned / Company Pool' }}
                                </p>
                                @if($asset->assignee)
                                    <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-medium text-white/80">
                                        {{ $asset->department_name }}
                                    </span>
                                @endif
                            </div>
                            @if($asset->assignee?->job_title || $asset->assignee?->position_title)
                                <p class="mt-1 text-xs text-white/60">
                                    {{ $asset->assignee->position_title ?: $asset->assignee->job_title }}
                                </p>
                            @endif
                        </div>

                        {{-- Location --}}
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Assigned Location</p>
                            <p class="mt-1 text-base font-semibold text-white">{{ $asset->location ?: ($asset->warehouse_location ?: 'CMIH Head Office') }}</p>
                        </div>

                        {{-- Owner --}}
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Legal Owner</p>
                            <p class="mt-1 text-base font-semibold text-white">{{ $asset->owner ?: 'CMIH Africa' }}</p>
                        </div>

                        {{-- Brand --}}
                        @if($asset->brand)
                            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Brand / Manufacturer</p>
                                <p class="mt-1 text-base font-semibold text-white">{{ $asset->brand }}</p>
                            </div>
                        @endif

                        {{-- Serial Number --}}
                        @if($asset->serial_number)
                            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:bg-white/[0.05]">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Serial Number</p>
                                <p class="mt-1 font-mono text-sm font-semibold text-white">{{ $asset->serial_number }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Notes / Description --}}
                @if($asset->description || $asset->notes)
                    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-white/45">Asset Description & Notes</p>
                        <div class="mt-2 text-sm text-white/70 leading-relaxed space-y-2">
                            {!! $asset->description ?: e($asset->notes) !!}
                        </div>
                    </div>
                @endif

                {{-- Footer Info --}}
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-white/10 pt-6 text-xs text-white/40">
                    <p>Registered: {{ $asset->created_at?->format('M d, Y') ?? 'N/A' }}</p>
                    <p class="text-center sm:text-right">Property of Concepts Management & Integrated Holdings (CMIH)</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
