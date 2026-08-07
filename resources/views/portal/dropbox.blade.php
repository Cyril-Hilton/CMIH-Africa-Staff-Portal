<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-white leading-tight">
            {{ __('Cloud Storage Hub') }}
        </h2>
    </x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Connection Status Alert -->
        <div class="relative overflow-hidden rounded-2xl border border-brand-white/10 bg-brand-black/40 p-6 backdrop-blur-xl">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    @if($isConnected)
                        <span class="flex h-3 w-3 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-brand-white">Connected to Cloud Storage</h3>
                            <p class="text-xs text-brand-white/60">Server offloading active. High-resolution campaign assets are securely mirrored in the cloud.</p>
                        </div>
                    @else
                        <span class="flex h-3 w-3 relative">
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-brand-white">Cloud Storage Offline</h3>
                            <p class="text-xs text-brand-white/60">Dropbox API credentials expired or not set in .env. Fallback to local asset server is active.</p>
                        </div>
                    @endif
                </div>
                <div>
                    <span class="rounded-lg bg-brand-white/5 border border-brand-white/10 px-3 py-1 text-xs text-brand-white font-mono uppercase">
                        Provider: Dropbox
                    </span>
                </div>
            </div>
        </div>

        <!-- File Browser -->
        <div class="relative overflow-hidden rounded-2xl border border-brand-white/10 bg-brand-black/40 p-8 backdrop-blur-xl">
            
            <!-- Path Navigation / Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs font-semibold tracking-wider text-brand-white/60 mb-6 uppercase flex-wrap">
                <a href="{{ route('portal.dropbox') }}" class="hover:text-brand-white transition">Root</a>
                @foreach($breadcrumbs as $bc)
                    <span class="text-brand-white/30">/</span>
                    <a href="{{ route('portal.dropbox', ['path' => $bc['path']]) }}" class="hover:text-brand-white transition">{{ $bc['name'] }}</a>
                @endforeach
            </div>

            <!-- Directory Grid -->
            @if($isConnected && count($files) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($files as $file)
                        @if($file['type'] === 'folder')
                            <!-- Folder Item -->
                            <a href="{{ route('portal.dropbox', ['path' => $file['path']]) }}" class="group flex items-center gap-4 p-4 rounded-xl border border-brand-white/5 bg-brand-white/5 hover:bg-brand-white/10 hover:border-brand-white/20 transition text-left">
                                <div class="text-amber-400">
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                                    </svg>
                                </div>
                                <div class="truncate">
                                    <h4 class="text-sm font-semibold text-brand-white truncate group-hover:text-brand-white transition">{{ $file['name'] }}</h4>
                                    <p class="text-xs text-brand-white/40">Folder</p>
                                </div>
                            </a>
                        @else
                            <!-- File Item -->
                            <div class="group flex items-center justify-between p-4 rounded-xl border border-brand-white/5 bg-brand-black/30 hover:bg-brand-white/5 hover:border-brand-white/10 transition">
                                <div class="flex items-center gap-4 truncate">
                                    <div class="text-cyan-400 shrink-0">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                            <line x1="16" y1="13" x2="8" y2="13"/>
                                            <line x1="16" y1="17" x2="8" y2="17"/>
                                            <polyline points="10 9 9 9 8 9"/>
                                        </svg>
                                    </div>
                                    <div class="truncate">
                                        <h4 class="text-sm font-semibold text-brand-white truncate" title="{{ $file['name'] }}">{{ $file['name'] }}</h4>
                                        <p class="text-xs text-brand-white/40 font-mono">{{ number_format($file['size'] / 1024, 1) }} KB</p>
                                    </div>
                                </div>
                                <div class="shrink-0 ml-2">
                                    <!-- Dynamic Download (Dropbox temporary links or view direct url) -->
                                    <!-- We will link directly to Dropbox sharing link preview -->
                                    <a href="{{ $file['path'] }}" target="_blank" class="p-2 rounded-lg bg-brand-white/5 border border-brand-white/10 text-brand-white hover:bg-brand-white/10 transition inline-block">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                            <polyline points="15 3 21 3 21 9"/>
                                            <line x1="10" y1="14" x2="21" y2="3"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="rounded-full bg-brand-white/5 p-4 border border-brand-white/10 mb-4 text-brand-white/40">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="9" y1="3" x2="9" y2="21"/>
                            <line x1="15" y1="3" x2="15" y2="21"/>
                            <line x1="3" y1="9" x2="21" y2="9"/>
                            <line x1="3" y1="15" x2="21" y2="15"/>
                        </svg>
                    </div>
                    @if($isConnected)
                        <h3 class="text-base font-semibold text-brand-white">Empty Cloud Folder</h3>
                        <p class="text-sm text-brand-white/40 max-w-sm mt-1">This directory doesn't contain any files or subdirectories yet.</p>
                    @else
                        <h3 class="text-base font-semibold text-brand-white">Cloud Explorer Unavailable</h3>
                        <p class="text-sm text-brand-white/40 max-w-sm mt-1">Configure valid `DROPBOX_ACCESS_TOKEN` credentials in your .env file to enable embedded file browsing.</p>
                    @endif
                </div>
            @endif

        </div>

    </div>
</div>
</x-app-layout>
