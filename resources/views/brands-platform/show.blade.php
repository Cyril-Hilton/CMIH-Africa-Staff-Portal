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

                        @if($activation?->banner_path)
                            <img src="{{ \App\Http\Controllers\Brands\BrandsPlatformController::storageUrl($activation->banner_path) }}" alt="{{ $activation->name }} banner" class="mt-6 aspect-[16/6] w-full rounded-lg object-cover">
                        @endif

                        <div class="mt-8 grid gap-3 sm:grid-cols-3">
                            <a href="#consumer-capture" class="rounded-md bg-brand-white px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-black transition hover:bg-brand-red hover:text-brand-white">Consumer</a>
                            <a href="{{ auth()->check() ? route('brands-platform.agency', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/15 px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white transition hover:border-brand-red hover:text-brand-red">Agency Staff</a>
                            <a href="{{ auth()->check() ? route('brands-platform.support', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/15 px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white transition hover:border-brand-red hover:text-brand-red">Supporting Staff</a>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <a href="{{ auth()->check() ? route('brands-platform.retail', $brand->slug ?: $brand->id) : route('login') }}" class="rounded-md border border-brand-white/15 px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/65 transition hover:border-brand-red hover:text-brand-red">Retail Partner</a>
                            <a href="{{ route('merchandisers.portal') }}" class="rounded-md border border-brand-white/15 px-4 py-3 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-brand-white/65 transition hover:border-brand-red hover:text-brand-red">Merchandisers Portal</a>
                        </div>
                        <p class="mt-4 text-xs leading-6 text-brand-white/45">Field updates are restricted to assigned teams and do not appear on the public brand page.</p>
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
                            <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">Verified</p>
                            <p class="mt-2 text-2xl font-semibold text-brand-white">{{ $metrics['verification_rate'] }}%</p>
                        </div>
                        <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                            <p class="text-[9px] font-bold uppercase tracking-wider text-brand-white/40">Updates</p>
                            <p class="mt-2 text-2xl font-semibold text-brand-white">{{ number_format($metrics['field_updates']) }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Brand Publications</p>
                        <div class="mt-4 grid gap-3">
                            @forelse($publications as $publication)
                                <article class="rounded-md border border-brand-white/10 bg-brand-black/35 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-[10px] uppercase tracking-wider text-brand-white/40">{{ $publication->category ?: 'Publication' }} - {{ $publication->published_at?->format('M d, Y') }}</p>
                                            <h2 class="mt-1 text-lg font-semibold text-brand-white">{{ $publication->title }}</h2>
                                        </div>
                                        @if($publication->image_path)
                                            <img src="{{ \App\Http\Controllers\Brands\BrandsPlatformController::storageUrl($publication->image_path) }}" alt="" class="h-14 w-20 rounded object-cover">
                                        @endif
                                    </div>
                                    <p class="mt-2 text-xs leading-6 text-brand-white/60">{{ $publication->summary ?: \Illuminate\Support\Str::limit(strip_tags($publication->body), 160) }}</p>
                                </article>
                            @empty
                                <p class="text-sm text-brand-white/40">No public brand updates have been published yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div id="consumer-capture" class="rounded-lg border border-brand-red/30 bg-brand-white/[0.05] p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Consumer Journey</p>
                            <h2 class="mt-2 text-2xl font-semibold text-brand-white">{{ $activation?->name ?: $brand->activation_name ?: 'Current Activation' }}</h2>
                            <p class="mt-2 text-xs leading-6 text-brand-white/55">{{ $activation?->description ?: $brand->activation_description }}</p>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="mt-4 rounded-md border border-brand-red/40 bg-brand-red/10 p-3 text-xs text-brand-white">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('brands-platform.consumer-entry.store', $brand->slug ?: $brand->id) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input name="name" required value="{{ old('name') }}" placeholder="Full name" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="phone" required value="{{ old('phone') }}" placeholder="Phone number" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="email" type="email" value="{{ old('email') }}" placeholder="Email address" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="location" value="{{ old('location') }}" placeholder="Location / branch" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <select name="age_band" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="">Age band</option>
                            @foreach(['18-22', '23-27', '28-35', '36+'] as $option)
                                <option @selected(old('age_band') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <select name="gender" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="">Gender</option>
                            @foreach(['Female', 'Male', 'Prefer not to say'] as $option)
                                <option @selected(old('gender') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <input name="current_choice" value="{{ old('current_choice') }}" placeholder="Current choice / competitor" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="preferred_channel" value="{{ old('preferred_channel') }}" placeholder="Preferred outlet / channel" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <select name="purchase_intent" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="">Purchase / conversion intent</option>
                            @foreach(['Definitely', 'Very likely', 'Likely', 'Maybe', 'Not interested'] as $option)
                                <option @selected(old('purchase_intent') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <select name="result_type" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            <option value="">Result / reward</option>
                            @foreach(['Sample Distributed', 'Bottle Sale / Conversion', 'Coupon Issued', 'Reward Issued', 'Qualified Lead'] as $option)
                                <option @selected(old('result_type') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <label class="flex gap-3 rounded-md border border-brand-white/10 bg-brand-black/35 px-3 py-3 text-xs leading-5 text-brand-white/65 sm:col-span-2">
                            <input type="checkbox" name="is_new_to_brand" value="1" class="mt-1" @checked(old('is_new_to_brand'))>
                            <span>This consumer is new to the brand, product or service proposition.</span>
                        </label>
                        <label class="flex gap-3 rounded-md border border-brand-white/10 bg-brand-black/35 px-3 py-3 text-xs leading-5 text-brand-white/65 sm:col-span-2">
                            <input type="checkbox" name="marketing_consent" value="1" class="mt-1" @checked(old('marketing_consent'))>
                            <span>Consumer agrees to receive future brand promotions and offers.</span>
                        </label>
                        <label class="flex gap-3 rounded-md border border-brand-white/10 bg-brand-black/35 px-3 py-3 text-xs leading-5 text-brand-white/65 sm:col-span-2">
                            <input type="checkbox" name="data_consent" value="1" required class="mt-1" @checked(old('data_consent'))>
                            <span>Consumer consents to this activation entry being stored and used for reporting.</span>
                        </label>
                        <button class="rounded-md bg-brand-red px-4 py-3 text-xs font-bold uppercase tracking-[0.22em] text-brand-white transition hover:bg-brand-white hover:text-brand-black sm:col-span-2">Send OTP</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
