<section
    x-data="{ open: {{ $errors->updatePassword->isNotEmpty() ? 'true' : 'false' }} }"
    class="space-y-4"
>
    {{-- Collapsed trigger button --}}
    <button
        type="button"
        @click="open = !open"
        class="group flex w-full items-center justify-between rounded-xl border border-brand-white/10 bg-brand-white/5 px-5 py-4 text-left transition hover:border-brand-white/20 hover:bg-brand-white/10"
    >
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-red/10 text-brand-red">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-brand-white">Change Password?</p>
                <p class="text-xs text-brand-white/50">Click to update your account password</p>
            </div>
        </div>
        <svg
            class="h-4 w-4 text-brand-white/40 transition-transform duration-300 group-hover:text-brand-white/70"
            :class="open ? 'rotate-180' : ''"
            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- Expandable password card --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-6 space-y-6"
    >
        <header class="space-y-1">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Security</p>
            <h2 class="text-2xl font-display text-brand-white">Update Password</h2>
            <p class="text-sm text-brand-white/70">Your password must be <span class="text-brand-white font-semibold">more than 8 characters</span> and include <span class="text-brand-white font-semibold">a letter, a number, and a symbol</span>.</p>
        </header>

        <form method="post" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            @method('put')

            <div>
                <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password" :value="__('New Password')" />
                <x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password"
                    oninput="cmihCheckPw(this.value)" />
                {{-- Password rule hint --}}
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span id="pw-len" class="inline-flex items-center gap-1 rounded-full border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[10px] uppercase tracking-wider text-brand-white/40">
                        <span id="pw-len-dot" class="w-1.5 h-1.5 rounded-full bg-brand-white/20"></span>
                        9+ characters
                    </span>
                    <span id="pw-alpha" class="inline-flex items-center gap-1 rounded-full border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[10px] uppercase tracking-wider text-brand-white/40">
                        <span id="pw-alpha-dot" class="w-1.5 h-1.5 rounded-full bg-brand-white/20"></span>
                        Contains letters
                    </span>
                    <span id="pw-num" class="inline-flex items-center gap-1 rounded-full border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[10px] uppercase tracking-wider text-brand-white/40">
                        <span id="pw-num-dot" class="w-1.5 h-1.5 rounded-full bg-brand-white/20"></span>
                        Contains numbers
                    </span>
                    <span id="pw-symbol" class="inline-flex items-center gap-1 rounded-full border border-brand-white/10 bg-brand-white/5 px-2.5 py-1 text-[10px] uppercase tracking-wider text-brand-white/40">
                        <span id="pw-symbol-dot" class="w-1.5 h-1.5 rounded-full bg-brand-white/20"></span>
                        Contains symbols
                    </span>
                </div>
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <script>
            function cmihCheckPw(val) {
                const len   = val.length > 8;
                const alpha = /[a-zA-Z]/.test(val);
                const num   = /[0-9]/.test(val);
                const symbol = /[^a-zA-Z0-9]/.test(val);
                function mark(id, dotId, ok) {
                    const el  = document.getElementById(id);
                    const dot = document.getElementById(dotId);
                    if (!el || !dot) return;
                    el.classList.toggle('border-emerald-500/40', ok);
                    el.classList.toggle('bg-emerald-500/10', ok);
                    el.classList.toggle('text-emerald-400', ok);
                    el.classList.toggle('border-brand-white/10', !ok);
                    el.classList.toggle('bg-brand-white/5', !ok);
                    el.classList.toggle('text-brand-white/40', !ok);
                    dot.classList.toggle('bg-emerald-400', ok);
                    dot.classList.toggle('bg-brand-white/20', !ok);
                }
                mark('pw-len',   'pw-len-dot',   len);
                mark('pw-alpha', 'pw-alpha-dot', alpha);
                mark('pw-num',   'pw-num-dot',   num);
                mark('pw-symbol', 'pw-symbol-dot', symbol);
            }
            </script>

            <div>
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>

                @if (session('status') === 'password-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-xs text-brand-white/60"
                    >Saved.</p>
                @endif
            </div>
        </form>
    </div>
</section>


