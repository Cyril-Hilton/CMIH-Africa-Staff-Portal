<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->name }} - Live Share | CMIH Africa</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">

    <!-- CSS Framework (Tailwind CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            black: '#0A0A0A',
                            white: '#FFFFFF',
                            red: '#E50914',
                            ash: '#808080',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        body {
            background-color: #0A0A0A;
            background-image: radial-gradient(circle at 50% -20%, rgba(229, 9, 20, 0.1) 0%, transparent 60%);
        }
    </style>
</head>
<body class="font-sans text-brand-white/95 min-h-screen pb-16">

    <!-- Header Navigation -->
    <nav class="border-b border-brand-white/10 py-6">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="text-xl font-display font-extrabold tracking-widest text-brand-white">CMIH<span class="text-brand-red">.</span></span>
                <span class="border-l border-brand-white/20 pl-3 text-xs uppercase tracking-widest text-brand-ash font-medium">Live Feed</span>
            </div>
            <div>
                <span class="rounded-full bg-brand-red/10 border border-brand-red/30 px-4 py-1 text-xs text-brand-red font-bold uppercase tracking-wider animate-pulse">
                    Live Client Portal
                </span>
            </div>
        </div>
    </nav>

    <!-- Main Content Grid -->
    <main class="max-w-7xl mx-auto px-6 mt-12 space-y-8">

        <!-- Status Alerts -->
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 text-sm text-emerald-400 max-w-4xl mx-auto">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[1fr_2fr] max-w-7xl mx-auto">
            
            <!-- LEFT COLUMN: Campaign Details & Milestones -->
            <div class="space-y-6">
                <!-- Info Card -->
                <div class="glass-panel rounded-3xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-6">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.25em] text-brand-ash">Campaign Overview</p>
                        <h1 class="text-2xl font-display font-extrabold text-brand-white mt-1">{{ $campaign->name }}</h1>
                        <p class="text-sm text-brand-red font-semibold mt-1">Client: {{ $campaign->client_name }}</p>
                    </div>

                    <div class="border-t border-brand-white/10 pt-4 space-y-3">
                        <div class="flex justify-between text-xs">
                            <span class="text-brand-ash">Start Date</span>
                            <span class="text-brand-white font-medium">{{ $campaign->start_date?->format('M d, Y') ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-brand-ash">End Date</span>
                            <span class="text-brand-white font-medium">{{ $campaign->end_date?->format('M d, Y') ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-brand-ash">Campaign Status</span>
                            <span class="rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3 py-0.5 text-[9px] uppercase font-bold text-emerald-400">
                                {{ strtoupper($campaign->status) }}
                            </span>
                        </div>
                        @if($campaign->duration !== null)
                            <div class="flex justify-between text-xs">
                                <span class="text-brand-ash">Duration</span>
                                <span class="text-brand-white font-semibold">{{ number_format($campaign->duration) }}</span>
                            </div>
                        @endif
                        @if($campaign->status_update)
                            <div class="flex justify-between text-xs">
                                <span class="text-brand-ash">Status Update</span>
                                <span class="text-brand-white font-semibold">{{ $campaign->status_update }}</span>
                            </div>
                        @endif
                        @if($campaign->projectLead)
                            <div class="flex justify-between text-xs">
                                <span class="text-brand-ash">Project Lead</span>
                                <span class="text-brand-white font-semibold">{{ $campaign->projectLead->name }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- KPI / Project Tasks Progress Card -->
                <div class="glass-panel rounded-3xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-4">
                    <h3 class="text-xs uppercase tracking-wider text-brand-white font-bold">🎯 Task Completion Target</h3>
                    
                    @php
                        $visibleTasks = $tasks->reject(fn ($task) => $task->completion_review_status === 'audit_task');
                        $totalCount = $visibleTasks->count();
                        $completedCount = $visibleTasks->filter->isApprovedForPerformance()->count();
                        $percent = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;
                    @endphp

                    <div class="space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-brand-ash">Progress ({{ $completedCount }} / {{ $totalCount }} Deliverables)</span>
                            <span class="text-brand-white font-bold">{{ $percent }}%</span>
                        </div>
                        <div class="w-full bg-brand-white/10 rounded-full h-2">
                            <div class="bg-brand-red h-2 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>

                    <div class="border-t border-brand-white/10 pt-4">
                        <h4 class="text-xs font-semibold text-brand-white uppercase mb-3">Milestone Progress Log</h4>
                        <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                            @forelse($tasks as $t)
                                <div class="flex items-start gap-3 text-xs">
                                    <div class="mt-0.5">
                                        @if($t->isApprovedForPerformance())
                                            <span class="text-emerald-400">✓</span>
                                        @else
                                            <span class="text-brand-ash">•</span>
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium {{ $t->isApprovedForPerformance() ? 'text-brand-white/60 line-through' : 'text-brand-white' }}">{!! $t->title !!}</p>
                                        <p class="text-[10px] text-brand-ash">Priority: {{ $t->priority }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-brand-white/30 italic">No milestone tasks defined for this campaign.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Photos Feed & Anonymous Upload -->
            <div class="space-y-6">

                <!-- Upload field photo widget -->
                <div class="glass-panel rounded-3xl p-6 border border-brand-white/10 bg-brand-white/5">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📸 Submit Live Field Photo</h3>
                    <form method="POST" action="{{ route('campaign.share.upload', $campaign->share_token) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold text-brand-ash uppercase mb-1">Select Image</label>
                                <input type="file" name="photo" accept="image/*" capture="environment" required class="w-full text-xs text-brand-ash file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-white/10 file:text-brand-white hover:file:bg-brand-white/20" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-brand-ash uppercase mb-1">Photo Caption</label>
                                <input type="text" name="caption" required placeholder="e.g. Accra Mall activation setup complete" class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                                Upload Field Photo
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Live Photos Feed Grid -->
                <div class="glass-panel rounded-3xl p-6 border border-brand-white/10 bg-brand-white/5 space-y-6">
                    <div>
                        <h3 class="text-lg font-display font-extrabold text-brand-white">Live Field Photo Feed</h3>
                        <p class="text-xs text-brand-white/60 mt-1">Real-time updates posted directly from field locations by execution supervisors.</p>
                    </div>

                    @if(count($photos) > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($photos as $photo)
                                <div class="rounded-2xl border border-brand-white/10 bg-brand-black/30 overflow-hidden flex flex-col">
                                    <div class="aspect-video bg-brand-black flex items-center justify-center overflow-hidden">
                                        <img src="{{ $photo->image_path }}" alt="Field update image" class="w-full h-full object-cover hover:scale-105 transition duration-500">
                                    </div>
                                    <div class="p-4 space-y-1">
                                        <p class="text-sm font-semibold text-brand-white">{{ $photo->caption }}</p>
                                        <p class="text-[10px] text-brand-ash">{{ $photo->created_at->diffForHumans() }} ({{ $photo->created_at->format('M d, H:i') }})</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="rounded-full bg-brand-white/5 p-4 border border-brand-white/10 mb-4 text-brand-white/30">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                    <circle cx="12" cy="13" r="3"/>
                                </svg>
                            </div>
                            <h4 class="text-sm font-semibold text-brand-white">No Field Photos Yet</h4>
                            <p class="text-xs text-brand-white/40 max-w-sm mt-1">Images uploaded from campaigns will compile here automatically in real time.</p>
                        </div>
                    @endif
                </div>

            </div>

        </div>

    </main>

</body>
</html>
