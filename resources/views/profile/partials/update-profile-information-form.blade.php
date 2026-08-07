<section class="space-y-6">
    <header class="space-y-2">
        <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Profile</p>
        <h2 class="text-2xl font-display text-brand-white">Profile Information</h2>
        <p class="text-sm text-brand-white/70">Update your name and refresh your profile photo for the portal directory.</p>
    </header>

    @if($errors->has('identity'))
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
            {{ $errors->first('identity') }}
        </div>
    @endif

    <div class="flex items-center gap-4">
        <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-full object-cover border border-brand-white/20" />
        <div>
            <p class="text-sm text-brand-white">{{ $user->name }}</p>
            <p class="text-xs text-brand-white/60">Role: {{ $user->job_title ?? 'Team Member' }}</p>
            @php
                $departmentLabel = \App\Models\User::departmentLabel($user->department);
                $roleLabel = \App\Models\User::roleLabel($user->access_role);
            @endphp
            <p class="text-xs uppercase tracking-[0.3em] text-brand-white/60">Access: {{ $roleLabel }}</p>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-white/60">{{ $departmentLabel }} Department</p>
        </div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5" enctype="multipart/form-data">
        @csrf
        @method('patch')

        @if($user->requested_position_title || $user->requested_department)
            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs text-amber-400">
                <p class="font-bold flex items-center gap-1.5">
                    <span>⏳</span> Pending Super Admin Approval
                </p>
                <ul class="list-disc pl-4 mt-2 space-y-1">
                    @if($user->requested_position_title)
                        <li>Requested Job Level: <strong>{{ $user->requested_position_title }}</strong></li>
                    @endif
                    @if($user->requested_department)
                        @php
                            $reqDeptLabel = \App\Models\User::departmentLabel($user->requested_department, $user->requested_department);
                        @endphp
                        <li>Requested Department: <strong>{{ $reqDeptLabel }}</strong></li>
                    @endif
                </ul>
                <p class="mt-2 text-brand-white/40">These requested changes will be applied once verified and approved by the Super Admin. You can modify them by selecting new options below and saving.</p>
            </div>
        @endif

        {{-- Full Name --}}
        <div>
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        {{-- Role / Job Title --}}
        <div>
            <x-input-label for="job_title" :value="__('Role / Job Title')" />
            <x-text-input id="job_title" name="job_title" type="text" :value="old('job_title', $user->job_title)" placeholder="e.g. Senior Designer, Account Manager" />
            <x-input-error class="mt-2" :messages="$errors->get('job_title')" />
        </div>

        {{-- Job Level (was "Position") --}}
        <div>
            <x-input-label for="position_title" :value="__('Job Level')" />
            <select id="position_title" name="position_title"
                class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0 transition">
                <option value="">— Select Job Level —</option>
                @foreach ([
                    'Support Staff'      => 'Support Staff',
                    'Executive'          => 'Executive',
                    'Senior Executive'   => 'Senior Executive',
                    'Assistant Manager'  => 'Assistant Manager',
                    'Manager'            => 'Manager',
                    'Department Head'    => 'Department Head',
                    'CVO'                => 'CVO',
                ] as $val => $label)
                    <option value="{{ $val }}" @selected(old('position_title', $user->requested_position_title ?? $user->position_title) === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('position_title')" />
        </div>

        {{-- Department --}}
        <div>
            <x-input-label for="department" :value="__('Department')" />
            <select id="department" name="department" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-0 transition">
                <option value="">Select department</option>
                @foreach ([
                    'hr_admin'            => 'HR & Admin',
                    'finance'             => 'Finance',
                    'client_relations'    => 'Client Relations',
                    'operations_projects' => 'Operations & Projects',
                    'brands_marketing'    => 'Brands & Marketing',
                    'creatives'           => 'Creatives',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('department', $user->requested_department ?? $user->department) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('department')" />
        </div>

        {{-- Employment Dates --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="date_of_birth" :value="__('Date of Birth')" />
                <x-text-input id="date_of_birth" name="date_of_birth" type="date" :value="old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d'))" />
                <p class="mt-1 text-[10px] text-brand-white/40">For internal records only — not shown on ID card</p>
                <x-input-error class="mt-2" :messages="$errors->get('date_of_birth')" />
            </div>
            <div>
                <x-input-label for="start_date" :value="__('Employment Date')" />
                <x-text-input id="start_date" name="start_date" type="date" :value="old('start_date', optional($user->start_date)->format('Y-m-d'))" />
                <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
            </div>
        </div>

        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3 text-xs text-brand-white/70">
            Staff ID number is generated automatically when your ID card details are complete.
        </div>

        {{-- Company Email --}}
        <div>
            <x-input-label for="email" :value="__('Company Email')" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-xs text-brand-white/70">
                        Your email address is unverified.
                        <button form="send-verification" class="underline text-brand-white/70 hover:text-brand-white">
                            Click here to re-send the verification email.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-xs text-green-200">
                            A new verification link has been sent to your email address.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Contact Email (now editable) --}}
        <div>
            <x-input-label for="contact_email" :value="__('Personal / Contact Email')" />
            <x-text-input id="contact_email" name="contact_email" type="email"
                :value="old('contact_email', $user->contact_email)"
                placeholder="Personal email for ID card delivery and notifications" />
            <p class="mt-1 text-[10px] text-brand-white/40">This is where your digital ID card will be emailed.</p>
            <x-input-error class="mt-2" :messages="$errors->get('contact_email')" />
        </div>

        {{-- Phone (read-only — set by admin) --}}
        <div>
            <x-input-label for="phone" :value="__('Phone Number')" />
            <x-text-input id="phone" name="phone" type="tel" :value="old('phone', $user->phone)" placeholder="+233..." autocomplete="tel" />
            <p class="mt-1 text-[10px] text-brand-white/40">Keep this updated for HR, operations, and emergency contact workflows.</p>
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        {{-- Profile Photo --}}
        <div>
            <x-input-label for="profile_photo" :value="__('Update Profile Photo')" />
            <input id="profile_photo" name="profile_photo" type="file" accept="image/*"
                class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white file:mr-4 file:rounded-full file:border-0 file:bg-brand-white/10 file:px-4 file:py-2 file:text-xs file:uppercase file:tracking-[0.3em] file:text-brand-white" />
            <x-input-error :messages="$errors->get('profile_photo')" class="mt-2" />
        </div>

        <x-identity-verification-fields :user="$user" />

        {{-- Mute Notification Sounds --}}
        <div class="flex items-start gap-3 rounded-lg border border-brand-white/10 bg-brand-black/20 p-4">
            <div class="flex h-5 items-center">
                <input id="mute_sounds" name="mute_sounds" type="checkbox" value="1"
                    @checked(old('mute_sounds', $user->mute_sounds))
                    class="h-4 w-4 rounded border-brand-white/10 bg-brand-black/40 text-brand-red focus:ring-brand-red" />
            </div>
            <div class="text-sm">
                <label for="mute_sounds" class="font-medium text-brand-white">Mute Notification Sounds</label>
                <p class="text-xs text-brand-white/60">Silence the audio bell chime for new messages, updates, and announcements.</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-xs text-emerald-400"
                >✓ Saved successfully.</p>
            @endif
        </div>
    </form>
</section>
