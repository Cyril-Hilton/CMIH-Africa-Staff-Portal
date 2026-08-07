<div
    x-data="{
        notifications: [],
        nextId: 1,
        init() {
            @if (session('status'))
                this.showNotification(@js(session('status')), 'success');
            @endif

            @if (session('success'))
                this.showNotification(@js(session('success')), 'success');
            @endif

            @if (session('error'))
                this.showNotification(@js(session('error')), 'error');
            @endif
            
            window.addEventListener('notify', event => {
                this.showNotification(event.detail);
            });
        },
        showNotification(detail, fallbackType = 'success') {
            const payload = typeof detail === 'string' ? { message: detail, type: fallbackType } : (detail || {});
            const id = this.nextId++;
            const item = {
                id,
                title: payload.title || '',
                message: payload.message || '',
                type: payload.type || fallbackType,
                url: payload.url || '',
                timeout: null,
            };

            if (!item.message && !item.title) {
                return;
            }

            this.notifications.unshift(item);
            if (this.notifications.length > 4) {
                const removed = this.notifications.pop();
                if (removed && removed.timeout) {
                    clearTimeout(removed.timeout);
                }
            }

            item.timeout = setTimeout(() => {
                this.removeNotification(id);
            }, payload.duration || 6500);
        },
        removeNotification(id) {
            const item = this.notifications.find(notification => notification.id === id);
            if (item && item.timeout) {
                clearTimeout(item.timeout);
            }
            this.notifications = this.notifications.filter(notification => notification.id !== id);
        },
        toneClasses(type) {
            return {
                success: 'border-emerald-500/25 bg-emerald-950/95 text-emerald-50',
                error: 'border-brand-red/30 bg-brand-red/95 text-white',
                info: 'border-brand-white/15 bg-brand-black/95 text-brand-white',
            }[type] || 'border-brand-white/15 bg-brand-black/95 text-brand-white';
        },
    }"
    class="fixed bottom-4 right-4 z-[70] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 sm:bottom-5 sm:right-5"
>
    <template x-for="item in notifications" :key="item.id">
        <div
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-[0.98]"
            :class="toneClasses(item.type)"
            class="flex gap-3 rounded-lg border px-4 py-3 shadow-2xl backdrop-blur-md"
        >
            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10">
                <template x-if="item.type === 'success'">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </template>
                <template x-if="item.type === 'error'">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </template>
                <template x-if="item.type !== 'success' && item.type !== 'error'">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </template>
            </div>

            <div class="min-w-0 flex-1">
                <p x-show="item.title" class="text-xs font-semibold leading-snug" x-text="item.title"></p>
                <p class="mt-0.5 text-xs leading-relaxed text-current/80" x-text="item.message"></p>
                <a x-show="item.url" :href="item.url" class="mt-2 inline-flex text-[10px] font-semibold uppercase tracking-[0.2em] text-current/80 hover:text-current">
                    Open
                </a>
            </div>

            <button type="button" @click="removeNotification(item.id)" class="h-8 w-8 shrink-0 rounded-full text-current/50 transition hover:bg-white/10 hover:text-current" aria-label="Dismiss notification">
                <svg class="mx-auto h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
    </template>
</div>
