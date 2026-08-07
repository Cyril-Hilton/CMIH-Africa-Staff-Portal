<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Apply as Field Agent</p>
            <h2 class="text-3xl font-display text-brand-white">Register Profile</h2>
            <p class="text-sm text-brand-white/70">Create your merchandiser account. All external agents require admin approval before activation.</p>
        </div>

        <form method="POST" action="{{ route('merchandisers.register') }}" class="space-y-4">
            @csrf

            <div>
                <x-input-label for="name" :value="__('Full Name')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required placeholder="Enter your full name" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Login Email Address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required placeholder="Enter login email" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contact_email" :value="__('Personal/Contact Email')" />
                <x-text-input id="contact_email" type="email" name="contact_email" :value="old('contact_email')" required placeholder="Enter personal email" />
                <x-input-error :messages="$errors->get('contact_email')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="phone" :value="__('Phone Number')" />
                <x-text-input id="phone" type="text" name="phone" :value="old('phone')" required placeholder="e.g. +23354XXXXXXX" />
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="date_of_birth" :value="__('Date of Birth')" />
                <x-text-input id="date_of_birth" type="date" name="date_of_birth" :value="old('date_of_birth')" required />
                <p class="text-[10px] text-brand-white/40 mt-0.5">Must be between 18 and 65 years of age.</p>
                <x-input-error :messages="$errors->get('date_of_birth')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" placeholder="At least 9 chars, e.g. Cmih2026!" />
                <p class="text-[10px] text-brand-ash mt-1">Use more than 8 characters with at least one letter, one number, and one symbol.</p>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required placeholder="Repeat password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>

            <x-primary-button class="w-full justify-center mt-2">
                Submit Registration
            </x-primary-button>
        </form>

        <p class="text-xs text-brand-white/60">
            Already registered? <a href="{{ route('merchandisers.login') }}" class="text-amber-500 hover:text-amber-400 font-semibold underline">Login here</a>.
        </p>

        <div class="pt-4 border-t border-brand-white/10 mt-4">
            <a href="{{ route('merchandisers.portal') }}" class="text-xs text-brand-white/60 hover:text-brand-white underline">
                ← Back to Gate selection
            </a>
        </div>
    </div>
</x-guest-layout>
