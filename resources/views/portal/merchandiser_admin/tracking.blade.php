<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Field Operations</p>
            <h2 class="text-3xl font-display text-brand-white">Live Tracking & Performance</h2>
        </div>
    </x-slot>

    <!-- Leaflet.js CSS & Chart.js CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .leaflet-container {
            background-color: #0b0a0a !important;
        }
        .leaflet-bar a {
            background-color: #1a0f0f !important;
            color: #fff !important;
            border-bottom: 1px solid rgba(239, 68, 68, 0.1) !important;
        }
        .leaflet-popup-content-wrapper, .leaflet-popup-tip {
            background: #120808 !important;
            color: #fff !important;
            border: 1px solid rgba(239, 68, 68, 0.2);
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.15);
        }
        /* Custom glow gradient background for this page */
        .glowing-bg {
            background: radial-gradient(circle at top right, rgba(239, 68, 68, 0.12), transparent 55%), radial-gradient(circle at bottom left, rgba(239, 68, 68, 0.04), transparent 50%), #09090b;
        }
    </style>

    <div x-data="{ activeTab: 'map' }" class="glowing-bg rounded-3xl p-6 border border-brand-white/10 space-y-6">
        
        <!-- Premium Tabs -->
        <div class="flex items-center gap-2 border-b border-brand-white/10 pb-4">
            <button @click="activeTab = 'map'" :class="activeTab === 'map' ? 'bg-red-500/10 text-red-500 border border-red-500/20' : 'text-brand-white/60 hover:text-brand-white'" class="px-5 py-2.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all flex items-center gap-2">
                🗺 Live Map & Routes
            </button>
            <button @click="activeTab = 'charts'" :class="activeTab === 'charts' ? 'bg-red-500/10 text-red-500 border border-red-500/20' : 'text-brand-white/60 hover:text-brand-white'" class="px-5 py-2.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all flex items-center gap-2">
                📊 Market Analytics & SKUs
            </button>
            <button @click="activeTab = 'leaderboard'" :class="activeTab === 'leaderboard' ? 'bg-red-500/10 text-red-500 border border-red-500/20' : 'text-brand-white/60 hover:text-brand-white'" class="px-5 py-2.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all flex items-center gap-2">
                🏆 Agent Leaderboard
            </button>
        </div>

        <!-- TAB 1: LIVE MAP -->
        <div x-show="activeTab === 'map'" style="display: none;">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 h-[calc(100vh-270px)] min-h-[500px]">
                <!-- Left panel: Merchandisers List -->
                <div class="glass-panel rounded-2xl p-5 border border-brand-white/10 bg-brand-black/40 flex flex-col justify-between overflow-y-auto hover:border-red-500/20 transition-all duration-300">
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash">Active Agents</h3>
                        <div class="space-y-2">
                            @forelse($activeMerchandisers as $m)
                                @php
                                    $isSelected = $selectedUser && $selectedUser->id === $m->id;
                                @endphp
                                <a href="{{ route('portal.merchandisers-admin.tracking', ['merchandiser_id' => $m->id]) }}" 
                                   class="block p-3 rounded-xl border transition-all text-left {{ $isSelected ? 'bg-red-500/10 border-red-500 text-brand-white shadow-[0_0_10px_rgba(239,68,68,0.15)]' : 'border-brand-white/5 bg-brand-black/20 text-brand-white/60 hover:text-brand-white hover:border-red-500/30' }}">
                                    <p class="font-bold text-xs">{{ $m->name }}</p>
                                    <p class="text-[10px] text-brand-white/40 mt-1">KD: {{ $m->merchandiserKd->name ?? 'Unmapped' }}</p>
                                    @if($isSelected)
                                        <span class="inline-flex mt-2 text-[9px] font-bold text-red-500 uppercase tracking-widest">
                                            Selected Route
                                        </span>
                                    @endif
                                </a>
                            @empty
                                <p class="text-xs text-brand-white/40 text-center py-4">No active field agents found.</p>
                            @endforelse
                        </div>
                    </div>
                    @if($selectedUser)
                        <div class="pt-4 border-t border-brand-white/10 mt-6 space-y-2">
                            <p class="text-[10px] uppercase text-brand-ash font-bold">Route Metrics</p>
                            <div class="text-xs space-y-1 text-brand-white/80">
                                <p>Agent: <strong class="text-brand-white">{{ $selectedUser->name }}</strong></p>
                                <p>Pings recorded today: <strong class="text-red-500">{{ count($selectedPath) }}</strong></p>
                            </div>
                            <a href="{{ route('portal.merchandisers-admin.tracking') }}" class="block text-center py-2 bg-brand-white/10 hover:bg-brand-white/15 text-brand-white font-bold rounded-lg text-[10px] uppercase tracking-wider transition-all">
                                Clear Route Path
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Right panel: Leaflet Map -->
                <div class="lg:col-span-3 rounded-2xl overflow-hidden border border-brand-white/10 bg-brand-black relative hover:border-red-500/20 transition-all duration-300">
                    <div id="tracking-map" class="w-full h-full min-h-[450px] z-10"></div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ANALYTICS & SKU CHARTS -->
        <div x-show="activeTab === 'charts'" class="space-y-6" style="display: none;">
            <!-- KPI Blocks -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-red-500/25 transition-all">
                    <p class="text-xs uppercase tracking-wider text-brand-ash">Total Visits Logged</p>
                    <p class="text-3xl font-display text-brand-white mt-2 font-bold">{{ $totalVisits }}</p>
                    <p class="text-[10px] text-brand-white/40 mt-1">Across all assigned retail outlets</p>
                </div>
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-red-500/25 transition-all">
                    <p class="text-xs uppercase tracking-wider text-brand-ash">Orders Sent to KDs</p>
                    <p class="text-3xl font-display text-red-500 mt-2 font-bold">{{ $totalOrders }}</p>
                    <p class="text-[10px] text-brand-white/40 mt-1">For replenishment requests</p>
                </div>
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-red-500/25 transition-all sm:col-span-2 lg:col-span-1">
                    <p class="text-xs uppercase tracking-wider text-brand-ash">Active Field Force</p>
                    <p class="text-3xl font-display text-brand-white mt-2 font-bold">{{ $activeMerchandisers->count() }}</p>
                    <p class="text-[10px] text-brand-white/40 mt-1">External paired merchandisers</p>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Store Movement Trend -->
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-red-500/20 transition-all">
                    <h4 class="text-xs uppercase tracking-[0.2em] font-bold text-brand-white mb-4">📈 Daily Store Movement Activities (Last 7 Days)</h4>
                    <div class="h-[280px]">
                        <canvas id="dailyVisitsChart"></canvas>
                    </div>
                </div>

                <!-- Top SKUs Orders -->
                <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-red-500/20 transition-all">
                    <h4 class="text-xs uppercase tracking-[0.2em] font-bold text-brand-white mb-4">🍩 Product Share of KD Orders (Top 5 SKUs)</h4>
                    <div class="h-[280px] flex items-center justify-center">
                        @if($topSkus->isEmpty())
                            <p class="text-xs text-brand-white/40">No SKU orders logged yet.</p>
                        @else
                            <canvas id="topSkusChart"></canvas>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: LEADERBOARD -->
        <div x-show="activeTab === 'leaderboard'" class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-black/40 hover:border-red-500/20 transition-all" style="display: none;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-brand-ash">Field Agents Performance Standings</h3>
                <span class="text-xs text-red-500 bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded">Real-Time</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-brand-white/70">
                    <thead class="uppercase tracking-wider text-brand-ash border-b border-brand-white/10">
                        <tr>
                            <th class="py-3 pl-3">Rank</th>
                            <th class="py-3">Agent</th>
                            <th class="py-3">Region & KD</th>
                            <th class="py-3">Visits Logged</th>
                            <th class="py-3">Lateness Check</th>
                            <th class="py-3 text-right pr-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($leaderboard as $index => $agent)
                            <tr class="hover:bg-brand-white/5 transition-colors">
                                <td class="py-4 pl-3">
                                    @if($index === 0)
                                        <span class="text-lg">🥇</span>
                                    @elseif($index === 1)
                                        <span class="text-lg">🥈</span>
                                    @elseif($index === 2)
                                        <span class="text-lg">🥉</span>
                                    @else
                                        <span class="font-bold text-brand-white/40">#{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="py-4">
                                    <span class="font-bold text-brand-white text-sm block">{{ $agent->name }}</span>
                                    <span class="text-brand-white/40">{{ $agent->email }}</span>
                                </td>
                                <td class="py-4">
                                    <p class="font-medium text-brand-white">{{ $agent->merchandiserRegion->name ?? 'None' }}</p>
                                    <p class="text-brand-white/40">{{ $agent->merchandiserKd->name ?? 'Unmapped' }}</p>
                                </td>
                                <td class="py-4 font-bold text-red-500 text-sm">
                                    {{ $agent->merchandiser_visits_count }} visits
                                </td>
                                <td class="py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-500/10 text-green-400 border border-green-500/20">
                                        95% On-Time
                                    </span>
                                </td>
                                <td class="py-4 text-right pr-3">
                                    <a href="{{ route('portal.merchandisers-admin.tracking', ['merchandiser_id' => $agent->id]) }}" class="px-2 py-1 bg-red-500/10 border border-red-500/20 text-red-500 hover:bg-red-500/20 uppercase font-semibold rounded text-[10px] transition-all">
                                        Track Route
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-brand-white/40">No merchandisers paired in directory.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Leaflet.js Script -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // ── Leaflet Map Setup ───────────────────────────────────
            var defaultCenter = [5.6037, -0.1870];
            var map = L.map('tracking-map', {
                zoomControl: true,
                attributionControl: false
            }).setView(defaultCenter, 12);

            // Dark Mode Map tiles (CartoDB)
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 20
            }).addTo(map);

            L.control.scale({ imperial: false }).addTo(map);

            var markers = @json($latestLocations);
            var markerGroup = L.featureGroup();

            markers.forEach(function (loc) {
                var popupText = `
                    <div style="font-family: Sora, sans-serif; font-size:11px;">
                        <h4 style="margin:0 0 5px; color:#ef4444; font-weight:700;">${loc.name}</h4>
                        <p style="margin:0 0 3px;">📞 Phone: ${loc.phone}</p>
                        <p style="margin:0;">🕒 Last Active: ${loc.recorded_at}</p>
                    </div>
                `;
                
                var marker = L.marker([loc.latitude, loc.longitude])
                    .bindPopup(popupText)
                    .addTo(map);
                
                markerGroup.addLayer(marker);
            });

            var path = @json($selectedPath);
            if (path && path.length > 0) {
                var latlngs = path.map(function(pt) {
                    return [pt.latitude, pt.longitude];
                });

                var polyline = L.polyline(latlngs, {
                    color: '#ef4444',
                    weight: 4,
                    opacity: 0.8,
                    dashArray: '5, 8'
                }).addTo(map);

                var startPt = path[0];
                var endPt = path[path.length - 1];

                L.circleMarker([startPt.latitude, startPt.longitude], {
                    radius: 6,
                    fillColor: '#ef4444',
                    color: '#fff',
                    weight: 2,
                    fillOpacity: 1
                }).bindPopup("<b>Route Start</b><br>Time: " + startPt.time).addTo(map);

                L.circleMarker([endPt.latitude, endPt.longitude], {
                    radius: 7,
                    fillColor: '#ef4444',
                    color: '#fff',
                    weight: 2,
                    fillOpacity: 1
                }).bindPopup("<b>Current Location</b><br>Time: " + endPt.time).addTo(map);

                map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
            } else if (markers.length > 0) {
                map.fitBounds(markerGroup.getBounds(), { padding: [50, 50] });
            }

            // ── Chart.js Setup ─────────────────────────────────────
            // 1. Line Chart: Daily Store Visits
            var visitsData = @json($dailyVisits);
            var visitsLabels = Object.keys(visitsData);
            var visitsValues = Object.values(visitsData);

            var ctxVisits = document.getElementById('dailyVisitsChart');
            if (ctxVisits) {
                new Chart(ctxVisits, {
                    type: 'line',
                    data: {
                        labels: visitsLabels,
                        datasets: [{
                            label: 'Store Visits',
                            data: visitsValues,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                ticks: { color: '#c5c6c7', stepSize: 1 }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#c5c6c7' }
                            }
                        }
                    }
                });
            }

            // 2. Doughnut Chart: Top SKUs
            var topSkusData = @json($topSkus);
            if (topSkusData && topSkusData.length > 0) {
                var skuLabels = topSkusData.map(item => item.name);
                var skuValues = topSkusData.map(item => parseInt(item.total_qty));

                var ctxSkus = document.getElementById('topSkusChart');
                if (ctxSkus) {
                    new Chart(ctxSkus, {
                        type: 'doughnut',
                        data: {
                            labels: skuLabels,
                            datasets: [{
                                data: skuValues,
                                backgroundColor: [
                                    '#ef4444',
                                    '#c084fc',
                                    '#38bdf8',
                                    '#fbbf24',
                                    '#34d399'
                                ],
                                borderWidth: 1,
                                borderColor: 'rgba(9, 9, 11, 0.8)'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        color: '#c5c6c7',
                                        font: { size: 10 }
                                    }
                                }
                            }
                        }
                    });
                }
            }
        });
    </script>
</x-app-layout>
