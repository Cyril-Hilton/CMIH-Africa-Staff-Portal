<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Website Editor</p>
            <h2 class="text-3xl font-display text-brand-white">Content & Media Control</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
             <!-- Specialized Modules -->
             <div class="glass-panel rounded-2xl p-6">
                <h3 class="text-lg font-semibold text-brand-white">Content Modules</h3>
                <p class="text-sm text-brand-white/60">Manage specialized dynamic sections of the website.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <a href="{{ route('admin.portfolio') }}" class="group relative overflow-hidden rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 hover:border-brand-red/50 transition-colors">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 rounded-lg bg-brand-red/10 text-brand-red">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            </div>
                            <h4 class="font-medium text-brand-white group-hover:text-brand-red transition-colors">Portfolio</h4>
                        </div>
                        <p class="text-xs text-brand-white/50">Manage brand albums, covers, and gallery images.</p>
                    </a>

                    <a href="{{ route('admin.events') }}" class="group relative overflow-hidden rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 hover:border-brand-red/50 transition-colors">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 rounded-lg bg-orange-500/10 text-orange-500">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><calendar-days width="24" height="24"/><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <h4 class="font-medium text-brand-white group-hover:text-brand-red transition-colors">Events</h4>
                        </div>
                        <p class="text-xs text-brand-white/50">Calendar, upcoming events, and schedules.</p>
                    </a>

                    <a href="{{ route('admin.brands') }}" class="group relative overflow-hidden rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 hover:border-brand-red/50 transition-colors">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 rounded-lg bg-pink-500/10 text-pink-500">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 9h15a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-15a2 2 0 0 1-2-2V11a2 2 0 0 1 2-2z"/><path d="M2.5 9V4.5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2V9"/><path d="M16.5 9V4a2 2 0 0 0-2-2h-5a2 2 0 0 0-2 2v5"/></svg>
                            </div>
                            <h4 class="font-medium text-brand-white group-hover:text-brand-red transition-colors">Brands</h4>
                        </div>
                        <p class="text-xs text-brand-white/50">Manage partner logos and client list.</p>
                    </a>
                </div>
            </div>

            <div class="glass-panel rounded-2xl p-6">
                <h3 class="text-lg font-semibold text-brand-white">Text Content</h3>
                <p class="text-sm text-brand-white/60">Edit the core messaging displayed on the public site.</p>
    
                <div class="mt-6 space-y-4">
                    @foreach ($fields as $key => $label)
                        @php
                            $existing = $content[$key]->value ?? ($defaults[$key] ?? '');
                        @endphp
                        <form method="POST" action="{{ route('admin.content.update') }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="key" value="{{ $key }}">
                            <input type="hidden" name="type" value="text">
                            <label class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $label }}</label>
                            <textarea name="value" rows="3" class="wysiwyg-editor w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" placeholder="Enter content">{{ $existing }}</textarea>
                            <button class="inline-flex items-center rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70 hover:bg-brand-white/10 hover:text-brand-white transition-colors">Save</button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>


        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-semibold text-brand-white">Image Content</h3>
            <p class="text-sm text-brand-white/60">Upload hero visuals and section imagery. Supports common image formats.</p>

            <div class="mt-6 space-y-6">
                @foreach ($imageFields as $key => $label)
                    @php
                        $existing = $content[$key]->value ?? null;
                        $preview = $existing ? Storage::disk('public')->url($existing) : asset('images/optimized/guinness-influencer-soiree-3b.jpg');
                    @endphp
                    <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $label }}</p>
                            @if ($existing)
                                <form method="POST" action="{{ route('admin.content.update') }}">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $key }}">
                                    <input type="hidden" name="type" value="image">
                                    <input type="hidden" name="value" value="">
                                    <button class="text-xs uppercase tracking-[0.3em] text-brand-red">Remove</button>
                                </form>
                            @endif
                        </div>
                        <img src="{{ $preview }}" alt="{{ $label }}" class="w-full rounded-xl object-cover" />
                        <form method="POST" action="{{ route('admin.content.update') }}" enctype="multipart/form-data" class="space-y-2">
                            @csrf
                            <input type="hidden" name="key" value="{{ $key }}">
                            <input type="hidden" name="type" value="image">
                            <input type="file" name="image" class="w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-xs file:uppercase file:tracking-[0.3em] file:text-brand-white" />
                            <button class="inline-flex items-center rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">Upload</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
