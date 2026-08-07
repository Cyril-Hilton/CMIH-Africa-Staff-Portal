@extends('layouts.site')

@section('title', $album->title . ' - CMIH Portfolio')

@section('content')
    <section class="section-padding bg-brand-black/70">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-10">
                <a href="{{ route('portfolio') }}" class="mb-6 inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-brand-ash hover:text-brand-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    Back to Portfolio
                </a>
                
                <div class="grid gap-8 lg:grid-cols-[1fr_0.4fr]">
                    <div class="space-y-4 reveal">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-red">{{ $album->brand }}</p>
                        <h1 class="text-4xl font-display text-brand-white">{{ $album->title }}</h1>
                        @if($album->description)
                            <div class="text-brand-white/70 max-w-2xl text-lg">{!! $album->description !!}</div>
                        @endif
                    </div>
                    @if($album->date)
                        <div class="flex items-start justify-start lg:justify-end reveal delay-300">
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Date</p>
                                <p class="mt-1 text-lg text-brand-white">{{ $album->date->format('F Y') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($album->images->isEmpty())
                <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-12 text-center text-brand-white/50">
                    No images in this album yet.
                </div>
            @else
                <div class="columns-1 gap-4 sm:columns-2 lg:columns-3 space-y-4">
                    @foreach ($album->images as $image)
                        <div class="relative group break-inside-avoid">
                            <a 
                                href="{{ asset('storage/' . $image->image_path) }}" 
                                data-lightbox="gallery" 
                                data-title="{{ $album->title }} - {{ $album->brand }}"
                                class="block overflow-hidden rounded-xl border border-brand-white/10 hover:border-brand-red/50 transition-colors"
                            >
                                <img 
                                    src="{{ asset('storage/' . $image->image_path) }}" 
                                    alt="{{ $album->title }} Image" 
                                    class="w-full object-cover transition duration-700 group-hover:scale-105"
                                    loading="lazy"
                                />
                                <div class="touch-visible absolute inset-0 bg-brand-black/50 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-brand-white"><path d="M15 3h6v6M14 10l6.1-6.1M9 21H3v-6M10 14l-6.1 6.1"/></svg>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
