@extends('layouts.site')

@section('title', $brand->name.' - CMIH Brands Platform')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr]">
                <div class="space-y-6">
                    <a href="{{ route('brands-platform.index') }}" class="text-xs font-bold uppercase tracking-[0.25em] text-brand-white/50 transition hover:text-brand-white">Back to brands</a>
                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">{{ $brand->category ?: 'Brand' }}</p>
                                <h1 class="mt-2 font-display text-5xl leading-none text-brand-white sm:text-6xl">{{ $brand->name }}</h1>
                                <p class="mt-4 max-w-2xl text-sm leading-7 text-brand-white/70">{{ $brand->description }}</p>
                            </div>
                            @if($brand->logoUrl())
                                <img src="{{ $brand->logoUrl('dark') ?: $brand->logoUrl() }}" alt="{{ $brand->name }} logo" class="h-20 max-w-36 object-contain">
                            @endif
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-3">
                            <a href="#consumer-capture" class="rounded-md bg-brand-white px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-black transition hover:bg-brand-red hover:text-brand-white">Consumer</a>
                            <a href="{{ auth()->check() ? route('brands-platform.agency', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/15 px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white transition hover:border-brand-red hover:text-brand-red">Agency Staff</a>
                            <a href="{{ auth()->check() ? route('brands-platform.agency', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/15 px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white transition hover:border-brand-red hover:text-brand-red">Supporting Staff</a>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">Reach</p>
                            <p class="mt-2 text-2xl font-semibold text-brand-white">{{ number_format($metrics['reached']) }}</p>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">Target</p>
                            <p class="mt-2 text-2xl font-semibold text-brand-white">{{ number_format($metrics['target']) }}</p>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">Rate</p>
                            <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $metrics['reach_rate'] }}%</p>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">Updates</p>
                            <p class="mt-2 text-2xl font-semibold text-brand-white">{{ number_format($metrics['field_updates']) }}</p>
                        </div>
                    </div>
                </div>

                <div id="consumer-capture" class="rounded-lg border border-brand-red/30 bg-brand-white/[0.05] p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Consumer Capture</p>
                            <h2 class="mt-2 text-2xl font-semibold text-brand-white">{{ $activation?->name ?: $brand->activation_name ?: 'Current Activation' }}</h2>
                            <p class="mt-2 text-xs leading-6 text-brand-white/55">{{ $activation?->description ?: $brand->activation_description }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('brands-platform.consumer-entry.store', $brand->slug ?: $brand->id) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input name="name" placeholder="Full name" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="phone" placeholder="Phone number" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="email" type="email" placeholder="Email address" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="location" placeholder="Location / branch" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <select name="age_band" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="">Age band</option>
                            <option>18-22</option>
                            <option>23-27</option>
                            <option>28-35</option>
                            <option>36+</option>
                        </select>
                        <select name="gender" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="">Gender</option>
                            <option>Female</option>
                            <option>Male</option>
                            <option>Prefer not to say</option>
                        </select>
                        <select name="result_type" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white sm:col-span-2">
                            <option value="">Result / reward</option>
                            <option>Sample Distributed</option>
                            <option>Bottle Sale / Conversion</option>
                            <option>Coupon Issued</option>
                            <option>Reward Issued</option>
                            <option>Qualified Lead</option>
                        </select>
                        <button class="rounded-md bg-brand-red px-4 py-3 text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black sm:col-span-2">Save Consumer Entry</button>
                    </form>
                </div>
            </div>

            <div class="mt-8 rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Activation Workspace</p>
                        <h2 class="mt-1 text-xl font-semibold text-brand-white">Field updates are restricted to assigned teams</h2>
                    </div>
                    <a href="{{ auth()->check() ? route('brands-platform.agency', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Open Staff View</a>
                </div>
                <div class="mt-5 grid gap-3 text-sm text-brand-white/65 md:grid-cols-3">
                    <div class="rounded-md border border-brand-white/10 bg-brand-black/30 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-white/40">Agency Staff</p>
                        <p class="mt-2">Assigned CMIH staff can submit field movement, activation evidence, and campaign updates.</p>
                    </div>
                    <div class="rounded-md border border-brand-white/10 bg-brand-black/30 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-white/40">Supporting Staff</p>
                        <p class="mt-2">Promoters and sales teams can record daily activation work once they are assigned to this brand.</p>
                    </div>
                    <div class="rounded-md border border-brand-white/10 bg-brand-black/30 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-white/40">Client View</p>
                        <p class="mt-2">Client reports use secure share links generated from the staff workspace.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
