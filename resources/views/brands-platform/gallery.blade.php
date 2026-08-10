@extends('layouts.site')

@section('title', 'Brands Evidence Gallery')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Gallery</p>
                    <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">{{ $selectedBrand?->name ?: 'Brand Evidence Gallery' }}</h1>
                    <p class="mt-2 text-sm text-brand-white/60">Verified activity images from brand teams and support staff.</p>
                </div>
                <a href="{{ route('brands-platform.index') }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Brands Home</a>
            </div>

            <div class="mb-6 flex gap-2 overflow-x-auto pb-1">
                <a href="{{ route('brands-platform.gallery') }}" class="shrink-0 rounded-full border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider {{ $selectedBrand ? 'text-brand-white/55 hover:text-brand-white' : 'bg-brand-white text-brand-black' }}">All Brands</a>
                @foreach($brands as $brand)
                    <a href="{{ route('brands-platform.brand-gallery', $brand->slug ?: $brand->id) }}" class="shrink-0 rounded-full border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider {{ $selectedBrand?->id === $brand->id ? 'bg-brand-red text-brand-white' : 'text-brand-white/55 hover:text-brand-white' }}">{{ $brand->name }}</a>
                @endforeach
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse($activities as $activity)
                    <article class="overflow-hidden rounded-lg border border-brand-white/10 bg-brand-white/[0.045]">
                        <div class="aspect-[4/3] bg-brand-black/60">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($activity->evidence_path) }}" alt="{{ $activity->brand?->name }} evidence" class="h-full w-full object-cover" loading="lazy">
                        </div>
                        <div class="space-y-2 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-brand-white">{{ $activity->brand?->name }}</p>
                                    <p class="text-[10px] uppercase tracking-wider text-brand-white/40">{{ \Illuminate\Support\Str::headline($activity->activity_type) }}</p>
                                </div>
                                <span class="rounded-full bg-brand-black/50 px-2 py-1 text-[10px] text-brand-white/55">{{ number_format($activity->units) }}</span>
                            </div>
                            <p class="text-xs text-brand-white/60">{{ $activity->location ?: 'No location' }} - {{ $activity->created_at?->format('M d, H:i') }}</p>
                            <p class="line-clamp-2 text-xs leading-5 text-brand-white/45">{{ $activity->notes ?: 'No notes added.' }}</p>
                            <p class="text-[10px] uppercase tracking-wider text-brand-white/35">{{ $activity->user?->name ?: 'Field team' }}</p>
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-8 text-sm text-brand-white/50 sm:col-span-2 lg:col-span-3 xl:col-span-4">
                        No evidence images have been uploaded for this view yet.
                    </div>
                @endforelse
            </div>

            <div class="mt-6">{{ $activities->links() }}</div>
        </div>
    </section>
@endsection
