<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Portal Settings</p>
                <h2 class="text-3xl font-display text-brand-white">My Profile</h2>
            </div>
            <div class="rounded-2xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs uppercase tracking-[0.3em] text-brand-white/70">
                <p>Previous login: {{ $user->previous_login_at?->format('M d, Y H:i') ?? 'N/A' }}</p>
                <p>
                    Last login:
                    {{ $user->last_login_at?->diffForHumans() ?? 'First time' }}
                    @if ($user->last_login_at)
                        ({{ $user->last_login_at->format('M d, Y H:i') }})
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="space-y-6">
            <div class="glass-panel rounded-2xl p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="glass-panel rounded-2xl p-6">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <aside class="space-y-6">
            @php
            $departmentLabel = \App\Models\User::departmentLabel($user->department);
            $roleLabel = \App\Models\User::roleLabel($user->access_role);
        @endphp
            <div class="space-y-3">
                <div class="id-card-shell mx-auto rounded-3xl border border-brand-red/40 bg-gradient-to-br from-brand-red/20 via-brand-black/70 to-brand-black p-4 sm:p-6 shadow-xl">
                    <div class="id-card-content">
                        <div class="flex items-center justify-between">
                            <img src="{{ asset('images/logo/logo-light.png') }}" alt="CMIH Africa" class="h-8 w-auto sm:h-10" />
                            <span class="id-card-meta text-brand-white/70">Staff ID Card</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}" class="id-card-photo rounded-2xl object-cover border border-brand-white/20" />
                            <div class="min-w-0">
                                <p class="id-card-meta text-brand-ash">Access: {{ $roleLabel }}</p>
                                <h3 class="id-card-name mt-1 font-semibold text-brand-white">{{ $user->name }}</h3>
                                <p class="id-card-meta mt-1 text-brand-white/60">{{ $departmentLabel }} Department</p>
                                <p class="id-card-field mt-1 text-brand-white/70">Role: {{ $user->job_title ?? 'Team Member' }}</p>
                                @if ($user->position_title)
                                    <p class="id-card-field text-brand-white/60">Position: {{ $user->position_title }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                            <div>
                                <span class="id-card-meta text-brand-ash">Staff ID</span>
                                <span class="id-card-field block text-brand-white">{{ $user->staff_id_number ?? 'Pending' }}</span>
                            </div>
                            <div>
                                <span class="id-card-meta text-brand-ash">Employment Date</span>
                                <span class="id-card-field block text-brand-white">{{ $user->start_date?->format('M d, Y') ?? 'Not set' }}</span>
                            </div>
                            <div>
                                <span class="id-card-meta text-brand-ash">Job Level</span>
                                <span class="id-card-field block text-brand-white">{{ $user->position_title ?? 'Team Member' }}</span>
                            </div>
                            <div>
                                <span class="id-card-meta text-brand-ash">ID Expiry</span>
                                <span class="id-card-field block text-brand-white">{{ $user->id_expires_at?->format('M d, Y') ?? 'Not set' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('portal.id-card') }}" class="inline-flex w-full items-center justify-center rounded-full border border-brand-white/20 px-4 py-2 text-[10px] uppercase tracking-[0.3em] text-brand-white/80 hover:text-brand-white sm:text-xs">
                    Download / Print ID Card
                </a>
            </div>

            <div class="glass-panel rounded-2xl p-6">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Account Status</p>
                <p class="mt-2 text-lg text-brand-white">{{ ucfirst($user->status) }}</p>
                <p class="mt-2 text-sm text-brand-white/60">Phone: {{ $user->phone }}</p>
                <p class="text-sm text-brand-white/60">Company Email: {{ $user->email }}</p>
                <p class="text-sm text-brand-white/60">Contact Email: {{ $user->contact_email ?? 'Not set' }}</p>
                <p class="text-sm text-brand-white/60">Access Level: {{ $roleLabel }}</p>
                <p class="text-sm text-brand-white/60">Role: {{ $user->job_title ?? 'Team Member' }}</p>
                @if ($user->position_title)
                    <p class="text-sm text-brand-white/60">Job Level: {{ $user->position_title }}</p>
                @endif
                <p class="text-sm text-brand-white/60">Staff ID: {{ $user->staff_id_number ?? 'Pending' }}</p>
                <p class="text-sm text-brand-white/60">Department: {{ $departmentLabel }}</p>
                @if ($user->residential_address)
                    <p class="mt-2 text-xs text-brand-white/40 uppercase tracking-widest">Address on file ✓</p>
                @endif
            </div>

            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5 flex flex-col items-center justify-center gap-4">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Leave Session</p>
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="w-full text-center rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-white transition-all">
                        👋 Log Out
                    </button>
                </form>
            </div>

            @if ($user->must_reset_password)
                <div class="rounded-2xl border border-brand-red/40 bg-brand-red/10 p-6">
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Action Required</p>
                    <p class="mt-2 text-sm text-brand-white">Please update your temporary password to secure your account.</p>
                </div>
            @endif
        </aside>
    </div>
</x-app-layout>


