<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="site-theme" content="{{ $site_theme ?? 'BOLDER and BETTER' }}">

        <title>{{ config('app.name', 'CMIH Africa') }} - Portal</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicon.png') }}">
        <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192.png') }}">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#E50914">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        @php
            $viteReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
        @endphp
        @if ($viteReady)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                * { box-sizing: border-box; }
                body { margin: 0; font-family: 'Sora', sans-serif; background: #000; color: #fff; }
                a { color: inherit; text-decoration: none; }
            </style>
        @endif
    </head>
    <body class="h-screen overflow-hidden bg-brand-black font-sans antialiased text-brand-white">
        <div class="h-screen overflow-hidden bg-inked"
             x-data="{
                 sidebarOpen: false,
                 sidebarCollapsed: false,
                 init() {
                     this.sidebarCollapsed = localStorage.getItem('portalSidebarCollapsed') === 'true';
                 },
                 persistSidebarPreference() {
                     localStorage.setItem('portalSidebarCollapsed', this.sidebarCollapsed ? 'true' : 'false');
                 },
                 toggleSidebar() {
                     if (window.matchMedia('(min-width: 1024px)').matches) {
                         this.sidebarCollapsed = ! this.sidebarCollapsed;
                         this.sidebarOpen = false;
                         this.persistSidebarPreference();
                         return;
                     }

                     this.sidebarOpen = ! this.sidebarOpen;
                 },
                 hideSidebar() {
                     if (window.matchMedia('(min-width: 1024px)').matches) {
                         this.sidebarCollapsed = true;
                         this.sidebarOpen = false;
                         this.persistSidebarPreference();
                         return;
                     }

                     this.sidebarOpen = false;
                 },
                 showSidebar() {
                     this.sidebarCollapsed = false;
                     this.persistSidebarPreference();

                     if (! window.matchMedia('(min-width: 1024px)').matches) {
                         this.sidebarOpen = true;
                     }
                 }
             }"
             @keydown.escape.window="sidebarOpen = false"
             x-effect="document.body.classList.toggle('overflow-hidden', sidebarOpen && window.innerWidth < 1024)">
            <div x-show="sidebarOpen" x-cloak
                 class="fixed inset-0 z-40 bg-brand-black/70 backdrop-blur-sm lg:hidden"
                 @click="sidebarOpen = false"></div>

            <div class="flex h-full min-h-0 overflow-hidden">
                @include('partials.portal-sidebar')

                <div class="flex min-h-0 flex-1 flex-col min-w-0">
                    @include('partials.portal-header')

                    <main id="portal-main-content"
                          data-silent-root
                          class="main-scrollbar-none min-h-0 flex-1 min-w-0 overflow-y-auto overflow-x-hidden overscroll-contain px-4 py-5 sm:px-6 sm:py-8 lg:px-10">
                        @isset($header)
                            <div class="mb-6">
                                {{ $header }}
                            </div>
                        @endisset

                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
        <x-confirm-modal />
        <x-notification />

        <style>
            /* Dark mode theme overrides for CKEditor 5 */
            :root {
                --ck-color-rect-border: rgba(255, 255, 255, 0.1) !important;
                --ck-color-base-border: rgba(255, 255, 255, 0.1) !important;
                --ck-color-toolbar-background: rgba(20, 20, 20, 0.95) !important;
                --ck-color-base-background: rgba(0, 0, 0, 0.5) !important;
                --ck-color-button-default-hover-background: rgba(255, 255, 255, 0.1) !important;
                --ck-color-button-on-background: rgba(255, 255, 255, 0.15) !important;
                --ck-color-button-on-hover-background: rgba(255, 255, 255, 0.2) !important;
                --ck-color-list-background: rgba(20, 20, 20, 0.95) !important;
                --ck-color-panel-background: rgba(20, 20, 20, 0.95) !important;
                --ck-color-panel-border: rgba(255, 255, 255, 0.1) !important;
                --ck-color-dropdown-panel-background: rgba(20, 20, 20, 0.95) !important;
                --ck-color-dropdown-panel-border: rgba(255, 255, 255, 0.1) !important;
            }
            .ck-editor__editable_inline {
                background-color: rgba(0, 0, 0, 0.4) !important;
                color: #fff !important;
                border-color: rgba(255, 255, 255, 0.1) !important;
                /* Generous default height  approx 15 lines of text */
                min-height: 320px !important;
                /* Allow the editor to grow naturally as content expands */
                transition: min-height 0.2s ease;
                /* Comfortable line spacing */
                line-height: 1.7 !important;
                font-size: 0.9rem !important;
            }
            /* Full-document editors (workspace, budgets) get even more room */
            .ck-editor--full .ck-editor__editable_inline {
                min-height: 560px !important;
            }
            .ck-editor__editable_inline:focus {
                border-color: rgba(239, 68, 68, 0.5) !important;
                outline: none !important;
            }
            .ck.ck-editor__main>.ck-editor__editable {
                background: rgba(0, 0, 0, 0.4) !important;
            }
            .ck-toolbar {
                background-color: rgba(20, 20, 20, 0.8) !important;
                border-color: rgba(255, 255, 255, 0.1) !important;
            }
            .ck-toolbar * {
                color: #fff !important;
            }
            .ck.ck-button:not(.ck-disabled):hover, a.ck.ck-button:not(.ck-disabled):hover {
                background: rgba(255, 255, 255, 0.1) !important;
            }
            .ck.ck-button.ck-on, a.ck.ck-button.ck-on {
                background: rgba(255, 255, 255, 0.2) !important;
            }
            .ck.ck-dropdown .ck-button.ck-dropdown__button {
                background: transparent !important;
            }
            .ck.ck-list {
                background: rgba(20, 20, 20, 0.95) !important;
            }
            .ck.ck-list__item .ck-button:hover {
                background: rgba(255, 255, 255, 0.1) !important;
            }
            .ck.ck-list__item .ck-button.ck-on {
                background: rgba(255, 255, 255, 0.2) !important;
            }
            .ck.ck-placeholder::before {
                color: rgba(255, 255, 255, 0.3) !important;
            }
            /* Hide scrollbar for Chrome, Safari and Opera */
            .scrollbar-none::-webkit-scrollbar {
                display: none;
            }
            /* Hide scrollbar for IE, Edge and Firefox */
            .scrollbar-none {
                -ms-overflow-style: none;  /* IE and Edge */
                scrollbar-width: none;  /* Firefox */
            }
            .cmih-native-notification-prompt {
                position: fixed;
                right: 1rem;
                bottom: 1rem;
                z-index: 65;
                display: grid;
                grid-template-columns: auto minmax(0, 1fr) auto;
                gap: 0.85rem;
                width: min(34rem, calc(100vw - 2rem));
                border: 1px solid rgba(255, 255, 255, 0.14);
                border-radius: 0.9rem;
                background: rgba(5, 5, 5, 0.96);
                box-shadow: 0 24px 70px rgba(0, 0, 0, 0.45);
                color: #fff;
                padding: 0.9rem;
                backdrop-filter: blur(18px);
            }
            .cmih-native-notification-prompt__icon {
                display: grid;
                place-items: center;
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 999px;
                background: rgba(226, 28, 30, 0.18);
                color: #ff5a5f;
            }
            .cmih-native-notification-prompt__icon svg,
            .cmih-native-notification-prompt__actions svg {
                width: 1rem;
                height: 1rem;
            }
            .cmih-native-notification-prompt__body {
                min-width: 0;
            }
            .cmih-native-notification-prompt__body p {
                margin: 0;
                font-size: 0.85rem;
                font-weight: 700;
                line-height: 1.35;
            }
            .cmih-native-notification-prompt__body span {
                display: block;
                margin-top: 0.2rem;
                color: rgba(255, 255, 255, 0.66);
                font-size: 0.72rem;
                line-height: 1.45;
            }
            .cmih-native-notification-prompt__actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .cmih-native-notification-prompt__actions button {
                min-height: 2.25rem;
                border: 0;
                border-radius: 999px;
                color: #fff;
                cursor: pointer;
            }
            .cmih-native-notification-prompt__actions [data-native-notification-enable] {
                background: #e21c1e;
                padding: 0 1rem;
                font-size: 0.68rem;
                font-weight: 800;
                letter-spacing: 0.16em;
                text-transform: uppercase;
            }
            .cmih-native-notification-prompt__actions [data-native-notification-dismiss] {
                display: grid;
                place-items: center;
                width: 2.25rem;
                background: rgba(255, 255, 255, 0.08);
                color: rgba(255, 255, 255, 0.72);
            }
            .cmih-native-notification-prompt__actions button:hover {
                filter: brightness(1.08);
            }
            main .glass-panel,
            main section,
            main article {
                max-width: 100%;
                min-width: 0;
            }
            main .overflow-x-auto {
                max-width: 100%;
                -webkit-overflow-scrolling: touch;
            }
            @media (max-width: 640px) {
                html,
                body {
                    max-width: 100%;
                    overflow-x: hidden;
                }
                main h1,
                main h2 {
                    overflow-wrap: anywhere;
                }
                main .glass-panel {
                    border-radius: 1rem;
                }
                .ck-editor__editable_inline {
                    min-height: 180px !important;
                }
                .ck-toolbar {
                    flex-wrap: wrap !important;
                }
                .cmih-native-notification-prompt {
                    grid-template-columns: auto minmax(0, 1fr);
                    right: 0.75rem;
                    bottom: 0.75rem;
                    width: calc(100vw - 1.5rem);
                }
                .cmih-native-notification-prompt__actions {
                    grid-column: 1 / -1;
                    justify-content: flex-end;
                }
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Responsive Sidebar Toggle is now handled by AlpineJS via sidebarOpen state on the parent element.

                window.loadCmihCkeditor = function() {
                    if (window.CKEDITOR) {
                        return Promise.resolve(window.CKEDITOR);
                    }

                    if (window.cmihCkeditorPromise) {
                        return window.cmihCkeditorPromise;
                    }

                    window.cmihCkeditorPromise = new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://cdn.ckeditor.com/ckeditor5/36.0.1/super-build/ckeditor.js';
                        script.async = true;
                        script.onload = () => window.CKEDITOR ? resolve(window.CKEDITOR) : reject(new Error('CKEditor did not initialize.'));
                        script.onerror = () => reject(new Error('CKEditor CDN failed to load.'));
                        document.head.appendChild(script);
                    });

                    return window.cmihCkeditorPromise;
                };

                window.initWysiwygEditors = function(root = document) {
                    const editors = Array.from(root.querySelectorAll('.wysiwyg-editor'))
                        .filter((textarea) => textarea.dataset.ckeditorReady !== 'true');

                    if (editors.length === 0) {
                        return Promise.resolve();
                    }

                    return window.loadCmihCkeditor()
                        .then(() => {
                            editors.forEach((textarea) => {
                    if (textarea.dataset.ckeditorReady === 'true') {
                        return;
                    }

                    if (typeof CKEDITOR === 'undefined') {
                        console.error('CKEditor CDN failed to load.');
                        return;
                    }

                    const form = textarea.closest('form');
                    const wasRequired = textarea.hasAttribute('required');
                    if (wasRequired) {
                        textarea.dataset.requiredBeforeEditor = 'true';
                        textarea.removeAttribute('required');
                    }

                    CKEDITOR.ClassicEditor
                        .create(textarea, {
                            toolbar: {
                                items: [
                                    'undo', 'redo', '|',
                                    'findAndReplace', 'selectAll', '|',
                                    'heading', '|',
                                    'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
                                    'bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'removeFormat', '|',
                                    'bulletedList', 'numberedList', 'todoList', '|',
                                    'outdent', 'indent', 'alignment', '|',
                                    'insertTable', 'link', 'blockQuote', 'horizontalLine', 'pageBreak', 'specialCharacters', '|',
                                    'sourceEditing'
                                ],
                                shouldNotGroupWhenFull: true
                            },
                            ui: {
                                // Full-document editors (workspace, budget content) get a tall canvas
                                // All other form fields get a generous 320px working space
                                viewportOffset: { top: 0 }
                            },
                            removePlugins: [
                                'CKBox', 'CKFinder', 'EasyImage', 'RealTimeCollaborativeComments',
                                'RealTimeCollaborativeTrackChanges', 'RealTimeCollaborativeRevisionHistory',
                                'PresenceList', 'Comments', 'TrackChanges', 'TrackChangesData',
                                'RevisionHistory', 'Pagination', 'WProofreader', 'MathType',
                                'WebSocketGateway', 'CloudServices', 'RealTimeCollaborativeEditing',
                                'ExportPdf', 'ExportWord'
                            ],
                            alignment: {
                                options: ['left', 'center', 'right', 'justify']
                            }
                        })
                        .then(editor => {
                            textarea.dataset.ckeditorReady = 'true';
                            textarea._ckeditorInstance = editor;
                            // Apply height after editor is created
                            const isFullDoc = textarea.id === 'content';
                            const editableEl = editor.ui.view.editable.element;
                            if (editableEl) {
                                editableEl.style.minHeight = isFullDoc ? '560px' : '320px';
                                if (wasRequired) {
                                    editableEl.setAttribute('aria-required', 'true');
                                }
                            }

                            // Save instance for external scripts (like file import parser)
                            window.editorInstances = window.editorInstances || {};
                            window.editorInstances[textarea.id || 'content'] = editor;
                            if (textarea.id === 'content') {
                                window.budgetEditor = editor;
                            }

                            const syncEditorToTextarea = () => {
                                textarea.value = editor.getData();
                            };

                            editor.model.document.on('change:data', syncEditorToTextarea);
                            syncEditorToTextarea();

                            if (form && form.dataset.ckeditorSubmitSync !== 'true') {
                                form.dataset.ckeditorSubmitSync = 'true';
                                form.addEventListener('submit', () => {
                                    form.querySelectorAll('.wysiwyg-editor').forEach((field) => {
                                        const instance = field._ckeditorInstance;

                                        if (instance) {
                                            field.value = instance.getData();
                                        }
                                    });
                                }, true);
                            }

                            // If inside form with enter-to-submit behavior (e.g. Chat)
                            if (textarea.dataset.enterSubmit === 'true') {
                                editor.editing.view.document.on('keydown', (evt, data) => {
                                    if (data.keyCode === 13 && !data.shiftKey) { // Enter key
                                        data.preventDefault();
                                        evt.stop();
                                        textarea.value = editor.getData();
                                        textarea.closest('form').submit();
                                    }
                                });
                            }
                        })
                        .catch(error => {
                            console.error(error);
                        });
                });
                        })
                        .catch((error) => {
                            console.error(error);
                        });
                };

                window.initWysiwygEditors(document);

                // PWA Service Worker Registration
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => {
                        navigator.serviceWorker.register('/sw.js')
                            .then((reg) => console.log('PWA Service Worker registered:', reg.scope))
                            .catch((err) => console.error('PWA Service Worker failed:', err));
                    });
                }
            });
        </script>

        @auth
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const notificationConfig = {
                    pollUrl: @json(route('portal.notifications.poll', [], false)),
                    defaultUrl: @json(route('portal.announcements', [], false)),
                    iconUrl: @json(asset('images/logo/favicon.png')),
                };

                window.playNotificationSound = function() {
                    if (window.userMuteSounds) return;

                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        if (!AudioContext) return;
                        
                        const ctx = new AudioContext();
                        
                        // Digital pure tone bell play (chime: E5 -> A5)
                        const osc1 = ctx.createOscillator();
                        const gain1 = ctx.createGain();
                        osc1.type = 'sine';
                        osc1.frequency.setValueAtTime(659.25, ctx.currentTime);
                        gain1.gain.setValueAtTime(0.2, ctx.currentTime);
                        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                        osc1.connect(gain1);
                        gain1.connect(ctx.destination);
                        
                        const osc2 = ctx.createOscillator();
                        const gain2 = ctx.createGain();
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(880, ctx.currentTime + 0.12);
                        gain2.gain.setValueAtTime(0.001, ctx.currentTime);
                        gain2.gain.setValueAtTime(0.2, ctx.currentTime + 0.12);
                        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
                        osc2.connect(gain2);
                        gain2.connect(ctx.destination);
                        
                        osc1.start(ctx.currentTime);
                        osc1.stop(ctx.currentTime + 0.4);
                        osc2.start(ctx.currentTime + 0.12);
                        osc2.stop(ctx.currentTime + 0.6);
                    } catch (err) {
                        console.warn('Playback of notification sound blocked or failed:', err);
                    }
                };

                window.userMuteSounds = {{ auth()->user()->mute_sounds ? 'true' : 'false' }};
                let serviceWorkerRegistration = null;

                const storageGet = (key) => {
                    try {
                        return window.localStorage.getItem(key);
                    } catch (error) {
                        return null;
                    }
                };

                const storageSet = (key, value) => {
                    try {
                        window.localStorage.setItem(key, value);
                    } catch (error) {
                        // Storage can be unavailable in private browsing.
                    }
                };

                const recentStoredTimestamp = (key, maxAgeHours = 16) => {
                    const value = storageGet(key);
                    if (!value) {
                        return '';
                    }

                    const timestamp = Date.parse(value);
                    if (!Number.isFinite(timestamp)) {
                        return '';
                    }

                    const maxAge = maxAgeHours * 60 * 60 * 1000;
                    return Date.now() - timestamp <= maxAge ? value : '';
                };

                const absoluteUrl = (url) => {
                    try {
                        return new URL(url || notificationConfig.defaultUrl, window.location.origin).href;
                    } catch (error) {
                        return new URL(notificationConfig.defaultUrl, window.location.origin).href;
                    }
                };

                const updateBadge = (selector, count) => {
                    document.querySelectorAll(selector).forEach((badge) => {
                        const value = Number(count || 0);
                        badge.textContent = value > 99 ? '99+' : String(value);
                        badge.setAttribute(selector.slice(1, -1), String(value));
                        badge.classList.toggle('hidden', value <= 0);
                    });
                };

                const seenIds = (storageKey) => {
                    try {
                        return JSON.parse(storageGet(storageKey) || '[]');
                    } catch (error) {
                        return [];
                    }
                };

                const rememberId = (storageKey, id, limit = 80) => {
                    if (!id) return false;

                    const key = String(id);
                    const seen = seenIds(storageKey);
                    if (seen.includes(key)) {
                        return false;
                    }

                    seen.unshift(key);
                    storageSet(storageKey, JSON.stringify(seen.slice(0, limit)));
                    return true;
                };

                const getServiceWorkerRegistration = async () => {
                    if (!('serviceWorker' in navigator)) {
                        return null;
                    }

                    if (serviceWorkerRegistration) {
                        return serviceWorkerRegistration;
                    }

                    const timeout = new Promise((resolve) => setTimeout(() => resolve(null), 1500));
                    serviceWorkerRegistration = await Promise.race([navigator.serviceWorker.ready, timeout]);
                    return serviceWorkerRegistration;
                };

                const nativeNotificationStatus = () => {
                    if (!('Notification' in window)) {
                        return 'unsupported';
                    }

                    if (!window.isSecureContext) {
                        return 'insecure';
                    }

                    if (Notification.permission === 'granted') {
                        return 'enabled';
                    }

                    if (Notification.permission === 'denied') {
                        return 'blocked';
                    }

                    return 'prompt';
                };

                const nativeStatusLabels = {
                    enabled: 'Device alerts on',
                    prompt: 'Enable alerts',
                    blocked: 'Alerts blocked',
                    insecure: 'HTTPS needed',
                    unsupported: 'Not supported',
                };

                const nativeStatusHints = {
                    enabled: 'Device alerts are enabled. Click to send a test alert.',
                    prompt: 'Click to enable device alerts for this browser.',
                    blocked: 'Device alerts are blocked in this browser. Allow notifications in browser settings.',
                    insecure: 'Open the portal with HTTPS to enable device alerts.',
                    unsupported: 'This browser does not support web notifications here.',
                };

                const nativeStatusColors = {
                    enabled: '#34d399',
                    prompt: '#fbbf24',
                    blocked: '#ef4444',
                    insecure: 'rgba(255, 255, 255, 0.35)',
                    unsupported: 'rgba(255, 255, 255, 0.35)',
                };

                const updateNativeNotificationControls = () => {
                    const status = nativeNotificationStatus();

                    document.querySelectorAll('[data-native-notification-toggle]').forEach((button) => {
                        const label = button.querySelector('[data-native-notification-label]');
                        const dot = button.querySelector('[data-native-notification-dot]');

                        button.dataset.nativeNotificationStatus = status;
                        button.title = nativeStatusHints[status] || nativeStatusHints.prompt;
                        button.setAttribute('aria-label', button.title);

                        if (label) {
                            label.textContent = nativeStatusLabels[status] || 'Device Alerts';
                        }

                        if (dot) {
                            dot.style.backgroundColor = nativeStatusColors[status] || nativeStatusColors.prompt;
                        }
                    });
                };

                const showNativeNotification = async (notif) => {
                    if (!('Notification' in window) || Notification.permission !== 'granted') {
                        return false;
                    }

                    const nativeId = notif.id || `${notif.title || 'CMIH'}:${notif.message || ''}:${notif.url || ''}`;
                    if (!rememberId('cmihSeenNativeNotificationIds', nativeId)) {
                        return false;
                    }

                    const title = notif.title || 'CMIH Africa';
                    const url = absoluteUrl(notif.url);
                    const options = {
                        body: notif.message || '',
                        icon: notificationConfig.iconUrl,
                        badge: notificationConfig.iconUrl,
                        tag: `cmih-${nativeId}`,
                        renotify: true,
                        data: { url },
                    };

                    try {
                        const registration = await getServiceWorkerRegistration();
                        if (registration && 'showNotification' in registration) {
                            await registration.showNotification(title, options);
                            return true;
                        }

                        const browserNotification = new Notification(title, options);
                        browserNotification.onclick = () => {
                            window.focus();
                            window.location.href = url;
                            browserNotification.close();
                        };
                        return true;
                    } catch (error) {
                        console.warn('Native notification failed:', error);
                        return false;
                    }
                };

                const sendNativeTestNotification = async () => {
                    const shown = await showNativeNotification({
                        id: `device-test-${Date.now()}`,
                        title: 'CMIH device alerts are on',
                        message: 'This is your test alert. Future CMIH reminders can appear in this device notification area.',
                        url: notificationConfig.defaultUrl,
                    });

                    if (shown) {
                        window.dispatchEvent(new CustomEvent('notify', {
                            detail: {
                                title: 'Device test sent',
                                message: 'Check your notification tray or notification center now.',
                                type: 'success',
                            }
                        }));
                        return true;
                    }

                    window.dispatchEvent(new CustomEvent('notify', {
                        detail: {
                            title: 'Device alert not shown',
                            message: 'The browser accepted the portal notification, but the device channel did not show it. Check OS notification settings for this browser.',
                            type: 'error',
                            duration: 9000,
                        }
                    }));
                    return false;
                };

                const requestNativePermissionAndTest = async () => {
                    const status = nativeNotificationStatus();

                    if (status === 'unsupported') {
                        window.dispatchEvent(new CustomEvent('notify', {
                            detail: {
                                title: 'Device alerts unavailable',
                                message: 'This browser does not support web notifications here. On iPhone, install the portal to the Home Screen and allow notifications.',
                                type: 'error',
                                duration: 9000,
                            }
                        }));
                        return;
                    }

                    if (status === 'insecure') {
                        window.dispatchEvent(new CustomEvent('notify', {
                            detail: {
                                title: 'HTTPS required',
                                message: 'Open https://cmih.africa before enabling device notifications.',
                                type: 'error',
                                duration: 9000,
                            }
                        }));
                        return;
                    }

                    if (status === 'blocked') {
                        window.dispatchEvent(new CustomEvent('notify', {
                            detail: {
                                title: 'Device alerts blocked',
                                message: 'Notifications are blocked for this browser. Allow cmih.africa in browser/site settings, then test again.',
                                type: 'error',
                                duration: 9000,
                            }
                        }));
                        updateNativeNotificationControls();
                        return;
                    }

                    if (status === 'prompt') {
                        try {
                            const permission = await Notification.requestPermission();
                            updateNativeNotificationControls();

                            if (permission !== 'granted') {
                                storageSet('cmihNotificationPromptDismissedAt', String(Date.now()));
                                window.dispatchEvent(new CustomEvent('notify', {
                                    detail: {
                                        title: 'Device alerts not enabled',
                                        message: 'The browser did not grant notification permission.',
                                        type: 'error',
                                    }
                                }));
                                return;
                            }
                        } catch (error) {
                            console.warn('Notification permission request failed:', error);
                            updateNativeNotificationControls();
                            return;
                        }
                    }

                    await getServiceWorkerRegistration();
                    await sendNativeTestNotification();
                    updateNativeNotificationControls();
                };

                const showInAppNotification = (notif) => {
                    const inAppId = notif.id || `${notif.title || 'CMIH'}:${notif.message || ''}:${notif.url || ''}`;
                    if (!rememberId('cmihSeenInAppNotificationIds', inAppId, 120)) {
                        return false;
                    }

                    window.dispatchEvent(new CustomEvent('notify', {
                        detail: {
                            title: notif.title || 'CMIH Africa',
                            message: notif.message || '',
                            type: 'info',
                            url: notif.url || notificationConfig.defaultUrl,
                        }
                    }));

                    return true;
                };

                const removePermissionPrompt = () => {
                    const prompt = document.querySelector('[data-native-notification-prompt]');
                    if (prompt) {
                        prompt.remove();
                    }
                };

                const shouldShowPermissionPrompt = () => {
                    if (!('Notification' in window) || !window.isSecureContext) {
                        return false;
                    }

                    if (Notification.permission !== 'default') {
                        return false;
                    }

                    const dismissedAt = Number(storageGet('cmihNotificationPromptDismissedAt') || 0);
                    const sevenDays = 7 * 24 * 60 * 60 * 1000;
                    return !dismissedAt || Date.now() - dismissedAt > sevenDays;
                };

                const showPermissionPrompt = () => {
                    if (!shouldShowPermissionPrompt() || document.querySelector('[data-native-notification-prompt]')) {
                        return;
                    }

                    const prompt = document.createElement('div');
                    prompt.setAttribute('data-native-notification-prompt', 'true');
                    prompt.className = 'cmih-native-notification-prompt';
                    prompt.innerHTML = `
                        <div class="cmih-native-notification-prompt__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                        </div>
                        <div class="cmih-native-notification-prompt__body">
                            <p>Enable CMIH alerts on this device</p>
                            <span>Tasks, approvals, messages, and announcements can appear in your device notifications.</span>
                        </div>
                        <div class="cmih-native-notification-prompt__actions">
                            <button type="button" data-native-notification-enable>Enable</button>
                            <button type="button" data-native-notification-dismiss aria-label="Dismiss notification prompt">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg>
                            </button>
                        </div>
                    `;

                    prompt.querySelector('[data-native-notification-enable]').addEventListener('click', async () => {
                        await requestNativePermissionAndTest();
                        removePermissionPrompt();
                    });

                    prompt.querySelector('[data-native-notification-dismiss]').addEventListener('click', () => {
                        storageSet('cmihNotificationPromptDismissedAt', String(Date.now()));
                        removePermissionPrompt();
                    });

                    document.body.appendChild(prompt);
                };

                let lastCheckedTime = recentStoredTimestamp('cmihLastNotificationPollAt');

                const checkNotifications = () => {
                    const url = lastCheckedTime
                        ? `${notificationConfig.pollUrl}?since=${encodeURIComponent(lastCheckedTime)}`
                        : notificationConfig.pollUrl;

                    fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => {
                        if (res.status === 401) {
                            clearInterval(notificationInterval);
                            return null;
                        }
                        return res.json();
                    })
                    .then(async data => {
                        if (!data) return;

                        lastCheckedTime = data.timestamp;
                        storageSet('cmihLastNotificationPollAt', lastCheckedTime);
                        updateBadge('[data-sidebar-notification-count]', data.unread_count);
                        updateBadge('[data-sidebar-message-count]', data.unread_message_count);

                        if (data.notifications && data.notifications.length > 0) {
                            let displayedInAppNotification = false;

                            for (const notif of data.notifications) {
                                const inAppShown = showInAppNotification(notif);
                                await showNativeNotification(notif);
                                displayedInAppNotification = displayedInAppNotification || inAppShown;
                            }

                            if (displayedInAppNotification) {
                                window.playNotificationSound();
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Error polling notifications:', err);
                    });
                };

                const checkNotificationsWhenVisible = () => {
                    if (!document.hidden) {
                        checkNotifications();
                    }
                };

                document.querySelectorAll('[data-native-notification-toggle]').forEach((button) => {
                    button.addEventListener('click', requestNativePermissionAndTest);
                });

                updateNativeNotificationControls();
                showPermissionPrompt();
                const notificationInterval = setInterval(checkNotificationsWhenVisible, 45000);

                setTimeout(checkNotificationsWhenVisible, 5000);

                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        updateNativeNotificationControls();
                        checkNotifications();
                    }
                });
            });
        </script>
        @endauth

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-clock-in-form]').forEach((form) => {
                    form.addEventListener('submit', () => {
                        const button = form.querySelector('[data-clock-in-submit]');
                        if (!button) return;

                        button.disabled = true;
                        button.classList.add('opacity-60', 'cursor-not-allowed');
                        button.innerHTML = 'Clocking In...';
                    });
                });
            });
        </script>

        @stack('scripts')
    </body>
</html>
