<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2">
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Staff Registration</p>
            <h2 class="text-3xl font-display text-brand-white">Join the Operating Hub</h2>
            <p class="text-sm text-brand-white/70">Provide your details and a profile photo. We will create your company email and send login credentials.</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        @if (session('generated_email'))
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3 text-sm text-brand-white/80">
                Your official CMIH identity is ready: <span class="text-brand-white">{{ session('generated_email') }}</span>. Check your inbox to secure it.
            </div>
        @endif

        @if (session()->has('email_sent') && !session('email_sent'))
            <div class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-200">
                We could not send the credential email. Please contact an admin to confirm your login details.
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-5" enctype="multipart/form-data">
            @csrf

            <div>
                <x-input-label for="name" :value="__('Full Name')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="John Doe" data-name-input />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="contact_email" :value="__('Personal Email (for credentials)')" />
                <x-text-input id="contact_email" type="email" name="contact_email" :value="old('contact_email')" required autocomplete="email" placeholder="john@example.com" />
                <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" :value="__('Phone Number')" />
                <x-text-input id="phone" type="text" name="phone" :value="old('phone')" required autocomplete="tel" placeholder="+233 54 220 4282" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="department" :value="__('Department')" />
                <select id="department" name="department" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                    <option value="">Select department</option>
                    @foreach (['hr_admin' => 'HR & Admin', 'finance' => 'Finance', 'client_relations' => 'Client Relations', 'operations_projects' => 'Operations / Projects', 'brands_marketing' => 'Brands & Marketing', 'creatives' => 'Creatives'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('department') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('department')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="job_title" :value="__('Role / Job Title')" />
                <x-text-input id="job_title" type="text" name="job_title" :value="old('job_title')" placeholder="Digital Director / Software Engineer" />
                <x-input-error :messages="$errors->get('job_title')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="position_title" :value="__('Position (Optional)')" />
                <x-text-input id="position_title" type="text" name="position_title" :value="old('position_title')" list="position-options" placeholder="Select or enter position" />
                <datalist id="position-options">
                    <option value="CEO"></option>
                    <option value="CVO"></option>
                    <option value="Manager"></option>
                    <option value="Head of Operations"></option>
                    <option value="Creative Director"></option>
                    <option value="Client Service Lead"></option>
                    <option value="Finance Lead"></option>
                    <option value="Brand Manager"></option>
                </datalist>
                <x-input-error :messages="$errors->get('position_title')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="date_of_birth" :value="__('Date of Birth')" />
                    <x-text-input id="date_of_birth" type="date" name="date_of_birth" :value="old('date_of_birth')" required />
                    <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="start_date" :value="__('Employment Date')" />
                    <x-text-input id="start_date" type="date" name="start_date" :value="old('start_date')" required />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>
            </div>

            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3 text-xs text-brand-white/70 space-y-1">
                <p>Staff ID number and ID expiry date are generated automatically after signup.</p>
                <p class="text-brand-white/50">🔒 Password Policy: new passwords must be more than 8 characters and include at least one letter, one number, and one symbol.</p>
            </div>

            <div>
                <x-input-label for="profile_photo" :value="__('Profile Photo (Required)')" />
                <input id="profile_photo" name="profile_photo" type="file" accept="image/*" required class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-xs file:uppercase file:tracking-[0.3em] file:text-brand-white" />
                <x-input-error :messages="$errors->get('profile_photo')" class="mt-2" />
            </div>

            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3 text-xs text-brand-white/70">
                Generated company email: <span class="text-brand-white" data-email-preview data-domain="{{ config('app.company_email_domain') }}"></span>
            </div>

            <x-primary-button class="w-full justify-center">
                Launch Your Profile
            </x-primary-button>
        </form>

        <p class="text-xs text-brand-white/60">
            Already have access? <a href="{{ route('login') }}" class="text-brand-white underline decoration-white/30">Sign in</a>.
        </p>
    </div>
</x-guest-layout>

