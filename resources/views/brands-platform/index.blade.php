@extends('layouts.site')

@section('title', 'CMIH Brands Platform')
@section('description', 'CMIH Africa Brands Platform for activations, consumer capture, field teams, merchandising, and client reports.')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto flex min-h-[calc(100vh-88px)] w-full max-w-7xl flex-col px-5 py-8 sm:px-8 lg:px-10">
            <div class="grid flex-1 gap-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
                <div class="space-y-6">
                    <div class="space-y-3">
                        <p class="text-xs font-bold uppercase tracking-[0.45em] text-brand-red">Activation. Engagement. Intelligence.</p>
                        <h1 class="font-display text-5xl leading-none text-brand-white sm:text-6xl lg:text-7xl">CMIH Brands Platform</h1>
                        <p class="max-w-xl text-sm leading-7 text-brand-white/70">
                            One workspace for brand activations, consumer entries, agency teams, supporting staff, live field updates, reports, and the embedded merchandiser portal.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('merchandisers.portal') }}" class="rounded-lg border border-brand-white/10 bg-brand-white px-4 py-3 text-center text-xs font-bold uppercase tracking-[0.22em] text-brand-black transition hover:bg-brand-red hover:text-brand-white">
                            Merchandiser Portal
                        </a>
                        <a href="{{ auth()->check() ? route('brands-platform.admin') : route('login') }}" class="rounded-lg border border-brand-red/50 bg-brand-red px-4 py-3 text-center text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black">
                            Admin Console
                        </a>
                        <a href="{{ auth()->check() ? route('brands-platform.gallery') : route('login') }}" class="rounded-lg border border-brand-white/10 px-4 py-3 text-center text-xs font-bold uppercase tracking-[0.22em] text-brand-white/65 transition hover:border-brand-white/30 hover:text-brand-white sm:col-span-2">
                            Evidence Gallery
                        </a>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Brands</p>
                            <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format($stats['brands']) }}</p>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Live Activations</p>
                            <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format($stats['live_activations']) }}</p>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Consumer Entries</p>
                            <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format($stats['consumer_entries']) }}</p>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Field Updates</p>
                            <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format($stats['field_updates']) }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid max-h-[76vh] gap-4 overflow-y-auto pr-1 sm:grid-cols-2">
                    @forelse($brands as $brand)
                        <article class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5 transition hover:border-brand-red/40 hover:bg-brand-white/[0.07]" style="--brand-primary: {{ $brand->primary_color ?: '#e50914' }};">
                            <div class="flex min-h-16 items-center justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">{{ $brand->category ?: 'Brand' }}</p>
                                    <h2 class="mt-1 text-2xl font-semibold text-brand-white">{{ $brand->name }}</h2>
                                </div>
                                @if($brand->logoUrl())
                                    <img src="{{ $brand->logoUrl('dark') ?: $brand->logoUrl() }}" alt="{{ $brand->name }} logo" class="h-12 max-w-24 object-contain" loading="lazy">
                                @endif
                            </div>

                            <p class="mt-4 text-sm font-semibold text-brand-white">{{ $brand->headline ?: 'Brand activation workspace' }}</p>
                            <p class="mt-2 line-clamp-3 text-xs leading-6 text-brand-white/60">{{ $brand->description ?: 'Manage activations, teams, entries, reports, and execution intelligence.' }}</p>

                            <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-md bg-brand-black/40 px-2 py-3">
                                    <p class="text-lg font-semibold text-brand-white">{{ $brand->activations_count }}</p>
                                    <p class="text-[9px] uppercase tracking-wider text-brand-white/45">Activations</p>
                                </div>
                                <div class="rounded-md bg-brand-black/40 px-2 py-3">
                                    <p class="text-lg font-semibold text-brand-white">{{ $brand->consumer_entries_count }}</p>
                                    <p class="text-[9px] uppercase tracking-wider text-brand-white/45">Entries</p>
                                </div>
                                <div class="rounded-md bg-brand-black/40 px-2 py-3">
                                    <p class="text-lg font-semibold text-brand-white">{{ $brand->field_activities_count }}</p>
                                    <p class="text-[9px] uppercase tracking-wider text-brand-white/45">Updates</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-2 sm:grid-cols-2">
                                <a href="{{ route('brands-platform.show', $brand->slug ?: $brand->id) }}" class="rounded-md bg-brand-white px-3 py-2 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-brand-black transition hover:bg-brand-red hover:text-brand-white">
                                    Open Brand
                                </a>
                                <a href="{{ auth()->check() ? route('brands-platform.agency', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/15 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-brand-white transition hover:border-brand-red hover:text-brand-red">
                                    Agency / Support
                                </a>
                                <a href="{{ auth()->check() ? route('brands-platform.brand-gallery', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/15 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-brand-white transition hover:border-brand-red hover:text-brand-red sm:col-span-2">
                                    Gallery
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-8 text-sm text-brand-white/60">
                            No brands have been added yet. Run migrations and seeders, then return here.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
