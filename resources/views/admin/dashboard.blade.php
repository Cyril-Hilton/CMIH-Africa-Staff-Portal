<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Admin Overview</p>
                <h2 class="text-3xl font-display text-brand-white">Super Admin Command Center</h2>
            </div>
            <div class="rounded-full border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">
                Total Staff: {{ $stats['total_staff'] }}
            </div>
        </div>
    </x-slot>

    <div class="mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-brand-red/20 to-brand-black border border-brand-red/20 relative">
        <div class="absolute top-0 right-0 p-4 opacity-10">
            <svg class="w-32 h-32 text-brand-red" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zm0 9l2.5-1.25L12 8.5l-2.5 1.25L12 11zm0 2.5l-5-2.5-5 2.5L12 22l10-8.5-5-2.5-5 2.5z"/></svg>
        </div>
        <div class="relative p-8">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-red mb-2">Theme of the Year</p>
            <h3 class="text-4xl font-display text-white uppercase">{{ $site_theme ?? 'BOLDER and BETTER' }}</h3>
            <p class="text-brand-white/60 mt-2 max-w-xl">Let's embody this theme in every activation, campaign, and interaction this year.</p>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <div class="glass-panel rounded-2xl p-5">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Pending Approvals</p>
            <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $stats['pending_approvals'] }}</p>
        </div>
        <div class="glass-panel rounded-2xl p-5">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Active Updates</p>
            <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $stats['active_updates'] }}</p>
        </div>
        <div class="glass-panel rounded-2xl p-5">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Open Tasks</p>
            <p class="mt-3 text-3xl font-semibold text-brand-white">{{ $stats['open_tasks'] }}</p>
        </div>
        <div class="glass-panel rounded-2xl p-5">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portal Health</p>
            <p class="mt-3 text-sm text-brand-white">All systems monitored</p>
        </div>
    </div>

    <div class="mt-8 glass-panel rounded-2xl p-6">
        @php
            $taskTotal = $stats['task_total'] ?? collect($taskStatusChart)->sum('count');
            $statusStyles = [
                'completed' => 'bg-emerald-500',
                'pending' => 'bg-brand-red',
                'overdue' => 'bg-amber-400',
            ];
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Staff Performance</p>
                <h3 class="text-lg font-semibold text-brand-white">Task Completion Pulse</h3>
            </div>
            <div class="rounded-full border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">
                Total tasks: {{ $taskTotal }}
            </div>
        </div>
        <div class="mt-6 space-y-4">
            @foreach ($taskStatusChart as $status)
                @php
                    $percentage = $taskTotal > 0 ? round(($status['count'] / $taskTotal) * 100) : 0;
                @endphp
                <div>
                    <div class="flex items-center justify-between text-xs uppercase tracking-[0.3em] text-brand-ash">
                        <span>{{ $status['label'] }}</span>
                        <span>{{ $status['count'] }} ({{ $percentage }}%)</span>
                    </div>
                    <div class="mt-2 h-2 w-full rounded-full bg-brand-white/10">
                        <div class="h-2 rounded-full {{ $statusStyles[$status['status']] ?? 'bg-brand-red' }}" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Remote Check-ins Geolocation Map -->
    <div class="mt-8 glass-panel rounded-2xl p-6">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Live Operations</p>
            <h3 class="text-lg font-semibold text-brand-white">Remote Check-In Geolocation Map</h3>
            <p class="text-xs text-brand-white/50 mt-1">Real-time pin drops of staff remote check-ins mapped via GPS coordinates.</p>
        </div>
        
        <!-- Leaflet.js Map Assets -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        
        <!-- Pre-define gm_authFailure for Google Maps error capturing -->
        <script>
            window.googleMapError = false;
            window.gm_authFailure = function() {
                console.error("Google Maps API error (auth/billing/invalid key). Swapping to Leaflet fallback.");
                window.googleMapError = true;
                if (typeof window.triggerLeafletFallback === 'function') {
                    window.triggerLeafletFallback();
                }
            };
        </script>
        
        <!-- Google Maps JS SDK -->
        @if(config('services.google.maps_api_key'))
            <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}"></script>
        @endif
        
        <div id="attendance-map" class="h-96 rounded-xl border border-brand-white/10 mt-6 overflow-hidden relative z-10"></div>
    </div>

    <script>
        // Shared helper to format location names and map addresses
        function getLocationInfo(lat, lng, source, remoteNotes) {
            if (source === 'Office Base') {
                return {
                    name: "Concepts Make It Happen.",
                    address: "No7 Afum Str, north legon, Accra."
                };
            }

            if (source === 'IP Geolocation' && remoteNotes) {
                const match = remoteNotes.match(/IP: [^\s]+ \(([^)]+)\)/);
                if (match && match[1]) {
                    return {
                        name: "IP Geolocation Base",
                        address: match[1]
                    };
                }
                return {
                    name: "IP Geolocation Base",
                    address: remoteNotes
                };
            }

            // For GPS Check-ins, we start with "Resolving..." and geocode dynamically in the browser
            return {
                name: "Resolving...",
                address: "Resolving..."
            };
        }

        document.addEventListener('DOMContentLoaded', function () {
            const attendanceLogs = @json($attendanceLogs);
            let leafletInitialized = false;

            window.triggerLeafletFallback = function() {
                if (leafletInitialized) return;
                leafletInitialized = true;
                console.log("Initializing Leaflet fallback map...");
                initLeafletMap(attendanceLogs);
            };

            function initLeafletMap(logs) {
                const container = document.getElementById('attendance-map');
                container.innerHTML = ''; // Clear container

                let centerLat = 5.6037;
                let centerLng = -0.1870;
                if (logs.length > 0) {
                    centerLat = parseFloat(logs[0].latitude);
                    centerLng = parseFloat(logs[0].longitude);
                }

                const map = L.map('attendance-map').setView([centerLat, centerLng], 12);

                // CartoDB Voyager tiles (light roadmap style, highly readable, Google Maps style)
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20
                }).addTo(map);

                if (logs.length > 0) {
                    const plottedCoords = {};
                    logs.forEach(log => {
                        let lat = parseFloat(log.latitude);
                        let lng = parseFloat(log.longitude);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            // Jittering for overlapping check-ins
                            const key = `${lat.toFixed(5)},${lng.toFixed(5)}`;
                            if (plottedCoords[key]) {
                                const count = plottedCoords[key];
                                plottedCoords[key] = count + 1;
                                const angle = count * (2 * Math.PI / 8); // 8 directions
                                const distance = 0.00015 * Math.ceil(count / 8); // Spiral offset
                                lat += Math.sin(angle) * distance;
                                lng += Math.cos(angle) * distance;
                            } else {
                                plottedCoords[key] = 1;
                            }

                            let statusColor = '#10b981'; // default green
                            if (log.source === 'GPS Check-In') {
                                statusColor = log.status === 'Late' ? '#ef4444' : '#10b981';
                            } else if (log.source === 'IP Geolocation') {
                                statusColor = '#3b82f6'; // Blue
                            } else if (log.source === 'Office Base') {
                                statusColor = '#a855f7'; // Purple
                            }

                            const clockInTime = log.clock_in_at 
                                ? new Date(log.clock_in_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })
                                : 'No Login/Clock In Time';

                            const origLat = parseFloat(log.latitude);
                            const origLng = parseFloat(log.longitude);
                            const locInfo = getLocationInfo(origLat, origLng, log.source, log.remote_notes);

                            const marker = L.circleMarker([lat, lng], {
                                radius: 8,
                                fillColor: statusColor,
                                color: '#fff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(map);

                            const popupContent = `
                                <div style="color: #000; font-family: sans-serif; padding: 4px; line-height: 1.4; min-width: 200px; max-width: 280px;">
                                    <strong style="font-size: 13px; color: #111;">${log.user.name}</strong><br>
                                    <span style="font-size: 11px; color: #666; font-weight: bold;">Dept: ${log.user.department ? log.user.department.toUpperCase().replace('_', ' ') : 'General'}</span><br>
                                    <span style="font-size: 11px; font-weight: bold; color: ${statusColor};">📍 Source: ${log.source}</span><br>
                                    <span style="font-size: 11px; color: #444;">current location; <b id="lf-loc-name-${log.user.id}">${locInfo.name}</b></span><br>
                                    <span style="font-size: 11px; color: #444;">location map address ; <b id="lf-loc-addr-${log.user.id}">${locInfo.address}</b></span><br>
                                    <span style="font-size: 11px; color: #444;">Last Active: <b>${clockInTime}</b></span><br>
                                    <span style="font-size: 11px; display: block; margin-top: 4px; border-top: 1px solid #eee; padding-top: 4px; color: #222;"><b>Details:</b> ${log.daily_objective}</span>
                                    ${log.remote_notes ? `<span style="font-size: 11px; display: block; color: #888; margin-top: 2px;"><b>Note:</b> ${log.remote_notes}</span>` : ''}
                                </div>
                            `;
                            marker.bindPopup(popupContent);

                            // Geocode dynamically on popup open if GPS Check-In
                            if (log.source === 'GPS Check-In') {
                                marker.on('popupopen', function() {
                                    const nameEl = document.getElementById(`lf-loc-name-${log.user.id}`);
                                    const addrEl = document.getElementById(`lf-loc-addr-${log.user.id}`);
                                    if (addrEl && addrEl.innerText === "Resolving...") {
                                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${origLat}&lon=${origLng}`)
                                            .then(r => r.json())
                                            .then(data => {
                                                if (data) {
                                                    const resolvedAddress = data.display_name || `${origLat.toFixed(4)}, ${origLng.toFixed(4)}`;
                                                    const address = data.address || {};
                                                    const currentLocName = address.suburb || address.neighbourhood || address.town || address.city || resolvedAddress.split(',')[0];
                                                    
                                                    if (nameEl) nameEl.innerText = currentLocName;
                                                    if (addrEl) addrEl.innerText = resolvedAddress;
                                                }
                                            })
                                            .catch(err => {
                                                console.error("Nominatim geocoding failed: ", err);
                                                if (nameEl) nameEl.innerText = "GPS Position";
                                                if (addrEl) addrEl.innerText = `${origLat.toFixed(4)}, ${origLng.toFixed(4)}`;
                                            });
                                    }
                                });
                            }
                        }
                    });
                } else {
                    L.popup()
                        .setLatLng([5.6037, -0.1870])
                        .setContent('<div style="color: #000; font-family: sans-serif; font-size: 12px; padding: 4px;">No remote check-in coordinate logs yet.</div>')
                        .openOn(map);
                }
            }

            function initGoogleMap(logs) {
                let centerLat = 5.6037;
                let centerLng = -0.1870;
                if (logs.length > 0) {
                    centerLat = parseFloat(logs[0].latitude);
                    centerLng = parseFloat(logs[0].longitude);
                }

                const mapOptions = {
                    center: { lat: centerLat, lng: centerLng },
                    zoom: 12
                };

                const map = new google.maps.Map(document.getElementById('attendance-map'), mapOptions);
                const infoWindow = new google.maps.InfoWindow();

                if (logs.length > 0) {
                    const plottedCoords = {};
                    logs.forEach(log => {
                        let lat = parseFloat(log.latitude);
                        let lng = parseFloat(log.longitude);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            // Jittering for overlapping check-ins
                            const key = `${lat.toFixed(5)},${lng.toFixed(5)}`;
                            if (plottedCoords[key]) {
                                const count = plottedCoords[key];
                                plottedCoords[key] = count + 1;
                                const angle = count * (2 * Math.PI / 8);
                                const distance = 0.00015 * Math.ceil(count / 8);
                                lat += Math.sin(angle) * distance;
                                lng += Math.cos(angle) * distance;
                            } else {
                                plottedCoords[key] = 1;
                            }

                            let statusColor = '#10b981'; // default green
                            if (log.source === 'GPS Check-In') {
                                statusColor = log.status === 'Late' ? '#ef4444' : '#10b981';
                            } else if (log.source === 'IP Geolocation') {
                                statusColor = '#3b82f6'; // Blue
                            } else if (log.source === 'Office Base') {
                                statusColor = '#a855f7'; // Purple
                            }

                            const clockInTime = log.clock_in_at 
                                ? new Date(log.clock_in_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })
                                : 'No Login/Clock In Time';

                            const origLat = parseFloat(log.latitude);
                            const origLng = parseFloat(log.longitude);
                            const locInfo = getLocationInfo(origLat, origLng, log.source, log.remote_notes);

                            const marker = new google.maps.Marker({
                                position: { lat: lat, lng: lng },
                                map: map,
                                title: log.user.name,
                                icon: {
                                    path: google.maps.SymbolPath.CIRCLE,
                                    fillColor: statusColor,
                                    fillOpacity: 0.9,
                                    scale: 8,
                                    strokeColor: "#ffffff",
                                    strokeWeight: 2
                                }
                            });

                            const popupContent = `
                                <div style="color: #000; font-family: sans-serif; padding: 4px; line-height: 1.4; max-width: 280px;">
                                    <strong style="font-size: 13px; color: #111;">${log.user.name}</strong><br>
                                    <span style="font-size: 11px; color: #666; font-weight: bold;">Dept: ${log.user.department ? log.user.department.toUpperCase().replace('_', ' ') : 'General'}</span><br>
                                    <span style="font-size: 11px; font-weight: bold; color: ${statusColor};">📍 Source: ${log.source}</span><br>
                                    <span style="font-size: 11px; color: #444;">current location; <b id="g-loc-name-${log.user.id}">${locInfo.name}</b></span><br>
                                    <span style="font-size: 11px; color: #444;">location map address ; <b id="g-loc-addr-${log.user.id}">${locInfo.address}</b></span><br>
                                    <span style="font-size: 11px; color: #444;">Last Active: <b>${clockInTime}</b></span><br>
                                    <span style="font-size: 11px; display: block; margin-top: 4px; border-top: 1px solid #eee; padding-top: 4px; color: #222;"><b>Details:</b> ${log.daily_objective}</span>
                                    ${log.remote_notes ? `<span style="font-size: 11px; display: block; color: #888; margin-top: 2px;"><b>Note:</b> ${log.remote_notes}</span>` : ''}
                                </div>
                            `;

                            marker.addListener('click', () => {
                                infoWindow.setContent(popupContent);
                                infoWindow.open(map, marker);

                                // Geocode dynamically on click if GPS Check-In
                                if (log.source === 'GPS Check-In') {
                                    setTimeout(() => {
                                        const nameEl = document.getElementById(`g-loc-name-${log.user.id}`);
                                        const addrEl = document.getElementById(`g-loc-addr-${log.user.id}`);
                                        if (addrEl && addrEl.innerText === "Resolving...") {
                                            const geocoder = new google.maps.Geocoder();
                                            geocoder.geocode({ location: { lat: origLat, lng: origLng } }, (results, status) => {
                                                if (status === "OK" && results[0]) {
                                                    const resolvedAddress = results[0].formatted_address;
                                                    let neighborhood = "";
                                                    let city = "";
                                                    for (let component of results[0].address_components) {
                                                        if (component.types.includes("sublocality") || component.types.includes("neighborhood")) {
                                                            neighborhood = component.long_name;
                                                        } else if (component.types.includes("locality")) {
                                                            city = component.long_name;
                                                        }
                                                    }
                                                    const currentLocName = neighborhood || city || results[0].formatted_address.split(',')[0];
                                                    
                                                    if (nameEl) nameEl.innerText = currentLocName;
                                                    if (addrEl) addrEl.innerText = resolvedAddress;
                                                } else {
                                                    if (nameEl) nameEl.innerText = "GPS Position";
                                                    if (addrEl) addrEl.innerText = `${origLat.toFixed(4)}, ${origLng.toFixed(4)}`;
                                                }
                                            });
                                        }
                                    }, 100);
                                }
                            });
                        }
                    });
                } else {
                    infoWindow.setContent('<div style="color: #000; font-family: sans-serif; font-size: 12px; padding: 4px;">No remote check-in coordinate logs yet.</div>');
                    infoWindow.setPosition({ lat: centerLat, lng: centerLng });
                    infoWindow.open(map);
                }
            }

            // Decide which map to initialize on page load
            setTimeout(function() {
                if (window.googleMapError) {
                    window.triggerLeafletFallback();
                } else if (typeof google !== 'undefined' && google.maps) {
                    try {
                        initGoogleMap(attendanceLogs);
                    } catch (e) {
                        console.error("Google Maps initialization failed, switching to Leaflet", e);
                        window.triggerLeafletFallback();
                    }
                } else {
                    console.warn("Google Maps script not detected, switching to Leaflet");
                    window.triggerLeafletFallback();
                }
            }, 300);
        });
    </script>


    <div class="mt-8 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-brand-white">Recent Sign-ups</h3>
                <a href="{{ route('admin.users') }}" class="text-xs uppercase tracking-[0.3em] text-brand-ash">Manage Users</a>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($recentSignups as $user)
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $user->created_at->format('M d, Y') }}</p>
                        <p class="mt-2 text-sm text-brand-white">{{ $user->name }}</p>
                        <p class="text-xs text-brand-white/60">Status: {{ $user->status }}</p>
                    </div>
                @empty
                    <p class="text-sm text-brand-white/60">No new recruits recently.</p>
                @endforelse
            </div>
        </div>

        <div class="glass-panel rounded-2xl p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-brand-white">Announcements</h3>
                <a href="{{ route('admin.announcements') }}" class="text-xs uppercase tracking-[0.3em] text-brand-ash">Manage</a>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($recentAnnouncements as $announcement)
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">{{ $announcement->title }}</p>
                        <p class="mt-2 text-xs text-brand-white/60">{!! nl2br(e($announcement->plainBody(180))) !!}</p>
                    </div>
                @empty
                    <p class="text-sm text-brand-white/60">No announcements. Time to broadcast something?</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
