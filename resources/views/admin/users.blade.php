<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Staff Management</p>
            <h2 class="text-3xl font-display text-brand-white">Manage Users</h2>
        </div>
    </x-slot>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-brand-red/30 bg-brand-red/10 px-4 py-3 text-sm text-red-200">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('temporary_password'))
        <div x-data class="mt-6 overflow-hidden rounded-2xl border border-brand-white/10 bg-brand-white/5 px-4 py-3 text-sm text-brand-white">
            Temporary password for {{ session('temporary_password_user') }} ({{ session('temporary_password_email') }}):
            <span class="font-semibold">{{ session('temporary_password') }}</span>
        </div>
    @endif

    <!-- Tab Controls -->
    <div class="flex flex-wrap items-center gap-3 sm:gap-4 mb-6 border-b border-brand-white/10 pb-4">
        <a href="{{ route('admin.users') }}" class="px-4 py-2 rounded-xl text-sm font-semibold uppercase tracking-wider transition-all {{ request('status') !== 'archived' ? 'bg-brand-white/10 text-brand-white border border-brand-white/15' : 'text-brand-white/60 hover:text-brand-white' }}">
            👥 Active & Pending Directory
        </a>
        <a href="{{ route('admin.users', ['status' => 'archived']) }}" class="px-4 py-2 rounded-xl text-sm font-semibold uppercase tracking-wider transition-all {{ request('status') === 'archived' ? 'bg-brand-white/10 text-brand-white border border-brand-white/15' : 'text-brand-white/60 hover:text-brand-white' }}">
            📦 Archive Directory
        </a>
    </div>

    @php
        $canManageAdmins = auth()->user()->hasRole('super_admin');
        $canArchiveUsers = auth()->user()->hasRole(['admin', 'super_admin']);
        $departmentLabels = [
            'hr_admin'            => 'HR & Admin',
            'finance'             => 'Finance',
            'client_relations'    => 'Client Relations',
            'operations_projects' => 'Operations & Projects',
            'brands_marketing'    => 'Brands & Marketing',
            'creatives'           => 'Creatives',
            'admin'               => 'HR & Admin',
            'transport'           => 'HR & Admin',
            'client_service'      => 'Client Relations',
            'operations'          => 'Operations & Projects',
            'brands'              => 'Brands & Marketing',
        ];
        $pendingProfileUsers = \App\Models\User::internalStaff()->where(function($q) {
            $q->whereNotNull('requested_position_title')
              ->orWhereNotNull('requested_department');
        })
        ->orderBy('requested_change_at', 'desc')
        ->get();
    @endphp

    @if(auth()->user()->access_role === 'super_admin' && $pendingProfileUsers->isNotEmpty())
        <div class="mb-8 glass-panel rounded-2xl p-6 border border-amber-500/20 bg-amber-500/5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-amber-400 mb-4">⏳ Pending Profile Level-up & Department Requests</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-brand-white/70">
                    <thead class="text-xs uppercase tracking-[0.2em] text-brand-ash">
                        <tr class="border-b border-brand-white/10">
                            <th class="py-2.5">Staff Member</th>
                            <th class="py-2.5">Requested Level (Job Level)</th>
                            <th class="py-2.5">Requested Department</th>
                            <th class="py-2.5">Requested At</th>
                            <th class="py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @forelse($pendingProfileUsers as $pUser)
                            <tr>
                                <td class="py-4">
                                    <div class="flex items-center gap-3">
                                        @if($pUser->profile_photo_path)
                                            <img src="{{ $pUser->profilePhotoUrl() }}" alt="{{ $pUser->name }}" class="w-10 h-10 rounded-xl object-cover border border-brand-white/10">
                                        @else
                                            <div class="w-10 h-10 rounded-xl bg-brand-white/10 flex items-center justify-center border border-brand-white/10">
                                                <span class="text-xs font-semibold text-brand-white/50">{{ substr($pUser->name, 0, 2) }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-medium text-brand-white">{{ $pUser->name }}</p>
                                            <p class="text-xs text-brand-white/40">{{ $pUser->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4">
                                    @if($pUser->requested_position_title)
                                        <div class="space-y-0.5">
                                            <span class="text-xs text-brand-white/40 line-through">{{ $pUser->position_title ?? 'None' }}</span>
                                            <span class="text-brand-white font-semibold">→ {{ $pUser->requested_position_title }}</span>
                                        </div>
                                    @else
                                        <span class="text-brand-white/40">No Change ({{ $pUser->position_title }})</span>
                                    @endif
                                </td>
                                <td class="py-4">
                                    @if($pUser->requested_department)
                                        @php
                                            $currDeptLabel = $departmentLabels[$pUser->department] ?? $pUser->department;
                                            $reqDeptLabel = $departmentLabels[$pUser->requested_department] ?? $pUser->requested_department;
                                        @endphp
                                        <div class="space-y-0.5">
                                            <span class="text-xs text-brand-white/40 line-through">{{ $currDeptLabel ?? 'Unassigned' }}</span>
                                            <span class="text-amber-400 font-semibold">→ {{ $reqDeptLabel }}</span>
                                        </div>
                                    @else
                                        @php
                                            $currDeptLabel = $departmentLabels[$pUser->department] ?? $pUser->department;
                                        @endphp
                                        <span class="text-brand-white/40">No Change ({{ $currDeptLabel }})</span>
                                    @endif
                                </td>
                                <td class="py-4 text-brand-white/60">
                                    {{ $pUser->requested_change_at ? $pUser->requested_change_at->diffForHumans() : 'N/A' }}
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.users.approve-profile', $pUser) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-green-500 hover:bg-green-600 text-black font-semibold text-xs uppercase tracking-wider transition-colors">
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.reject-profile', $pUser) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-brand-red/20 border border-brand-red/30 hover:bg-brand-red/30 text-brand-red font-semibold text-xs uppercase tracking-wider transition-colors">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-brand-white/40">No pending requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div x-data class="glass-panel rounded-2xl p-4 sm:p-6">
        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <table class="w-full min-w-[2240px] table-fixed text-left text-sm text-brand-white/70">
                <colgroup>
                    <col class="w-[250px]">
                    <col class="w-[145px]">
                    <col class="w-[190px]">
                    <col class="w-[145px]">
                    <col class="w-[320px]">
                    <col class="w-[320px]">
                    <col class="w-[150px]">
                    @if (auth()->user()->access_role === 'super_admin')
                        <col class="w-[150px]">
                    @endif
                    <col class="w-[175px]">
                    <col class="w-[175px]">
                    <col class="w-[270px]">
                </colgroup>
                <thead class="text-xs uppercase tracking-[0.3em] text-brand-ash">
                    <tr>
                        <th class="px-4 py-3">
                            <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'name', 'direction' => request('sort') === 'name' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                Staff
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'name' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'name' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        <th class="px-4 py-3">
                            <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'access_role', 'direction' => request('sort') === 'access_role' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                Role
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'access_role' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'access_role' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        <th class="px-4 py-3">
                            <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'department', 'direction' => request('sort') === 'department' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                Department
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'department' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'department' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        <th class="px-4 py-3">
                            <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'status', 'direction' => request('sort') === 'status' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                Status
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'status' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'status' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        <th class="px-5 py-3">
                            <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'email', 'direction' => request('sort') === 'email' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                Company Email
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'email' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'email' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        <th class="px-5 py-3">Contact Email</th>
                        <th class="px-4 py-3">
                             <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'phone', 'direction' => request('sort') === 'phone' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                Phone
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'phone' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'phone' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        @if (auth()->user()->access_role === 'super_admin')
                            <th class="px-4 py-3">Date of Birth</th>
                        @endif
                        <th class="px-4 py-3">
                             <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'id_expires_at', 'direction' => request('sort') === 'id_expires_at' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                ID Expiry
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'id_expires_at' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'id_expires_at' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        <th class="px-4 py-3">
                             <a href="{{ route('admin.users', array_merge(request()->query(), ['sort' => 'last_login_at', 'direction' => request('sort') === 'last_login_at' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}" class="flex items-center gap-1 group">
                                Login Activity
                                <div class="flex flex-col">
                                    <svg class="w-2 h-2 {{ request('sort') === 'last_login_at' && request('direction') === 'asc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M5 15l7-7 7 7" /></svg>
                                    <svg class="w-2 h-2 {{ request('sort') === 'last_login_at' && request('direction') === 'desc' ? 'text-brand-red' : 'text-brand-white/20 group-hover:text-brand-white/50' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b border-brand-white/5 hover:bg-brand-white/5 transition-colors group">
                            <td class="px-4 py-4 align-middle">
                                <div class="flex items-center gap-3">
                                    @if($user->profile_photo_path)
                                        <img src="{{ $user->profilePhotoUrl() }}" 
                                             alt="{{ $user->name }}" 
                                             class="w-10 h-10 shrink-0 rounded-xl object-cover border border-brand-white/10 group-hover:border-brand-red/50 transition-colors">
                                    @else
                                        <div class="w-10 h-10 shrink-0 rounded-xl bg-brand-white/10 flex items-center justify-center border border-brand-white/10 group-hover:border-brand-red/50 transition-colors">
                                            <span class="text-xs font-semibold text-brand-white/50">{{ substr($user->name, 0, 2) }}</span>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-medium leading-snug text-brand-white break-words">{{ $user->name }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-brand-white/50 break-words">Staff ID: {{ $user->staff_id_number ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                <span class="inline-flex w-fit items-center whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-white/10 text-brand-white/70 border border-brand-white/5">
                                    {{ ucfirst(str_replace('_', ' ', $user->access_role)) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                <span class="block truncate capitalize" title="{{ $user->department }}">{{ $user->department }}</span>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                @if($user->status === 'active')
                                    <span class="inline-flex w-fit items-center gap-1.5 whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/10">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                        Active
                                    </span>
                                @elseif($user->status === 'pending')
                                    <span class="inline-flex w-fit items-center gap-1.5 whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-500/10 text-yellow-400 border border-yellow-500/10">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
                                        Pending
                                    </span>
                                @else
                                    <span class="inline-flex w-fit items-center gap-1.5 whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/10">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 align-middle">
                                <span class="block max-w-[270px] overflow-hidden text-ellipsis whitespace-nowrap rounded-lg border border-brand-white/5 bg-brand-black/20 px-3 py-2 text-brand-white/70" title="{{ $user->email }}">
                                    {{ $user->email ?: 'N/A' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 align-middle">
                                <span class="block max-w-[270px] overflow-hidden text-ellipsis whitespace-nowrap rounded-lg border border-brand-white/5 bg-brand-black/20 px-3 py-2 text-brand-white/70" title="{{ $user->contact_email }}">
                                    {{ $user->contact_email ?: 'N/A' }}
                                </span>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                <span class="block whitespace-nowrap text-brand-white/60">{{ $user->phone ?: 'N/A' }}</span>
                            </td>
                            @if (auth()->user()->access_role === 'super_admin')
                                <td class="px-4 py-4 align-middle text-brand-white/60">
                                    {{ $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('M d, Y') : 'N/A' }}
                                </td>
                            @endif
                            <td class="px-4 py-4 align-middle">
                                <div class="space-y-1.5">
                                    @if($user->id_expires_at && $user->id_expires_at->isPast())
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-brand-red/10 text-brand-red border border-brand-red/20 w-fit">
                                            <span class="w-1.5 h-1.5 rounded-full bg-brand-red"></span>
                                            Expired
                                        </span>
                                    @endif
                                    <form method="POST" action="{{ route('admin.users.id-expiry', $user) }}" class="flex flex-nowrap items-center gap-1.5">
                                        @csrf
                                        <input type="date" name="id_expires_at" 
                                               value="{{ $user->id_expires_at ? $user->id_expires_at->toDateString() : '' }}" 
                                               class="rounded bg-brand-black/40 border border-brand-white/10 px-2 py-1 text-xs text-brand-white focus:border-brand-red focus:ring-0 w-[125px]">
                                        <button type="submit" class="p-1 rounded text-brand-white/50 hover:text-brand-white hover:bg-brand-white/10 transition-colors" title="Save Expiry Date">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-middle">
                                @if($user->last_login_at)
                                    <div class="flex flex-col whitespace-nowrap">
                                        <span class="text-brand-white/80">{{ \Carbon\Carbon::parse($user->last_login_at)->diffForHumans() }}</span>
                                        <span class="text-xs text-brand-white/40">{{ \Carbon\Carbon::parse($user->last_login_at)->format('M d, H:i') }}</span>
                                    </div>
                                @else
                                    <span class="text-brand-white/40">Never</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 align-middle text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if($user->status === 'archived')
                                        @if($canArchiveUsers)
                                        <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-2 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20 text-xs uppercase tracking-[0.3em] hover:bg-amber-500/20 font-bold transition-all" title="Restore User">
                                                Restore User
                                            </button>
                                        </form>
                                        @endif
                                    @else
                                        @if($user->id_expires_at && $user->id_expires_at->isPast())
                                            <form method="POST" action="{{ route('admin.users.id-expiry', $user) }}">
                                                @csrf
                                                <input type="hidden" name="id_expires_at" value="{{ now()->addYear()->toDateString() }}">
                                                <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs uppercase tracking-[0.3em] hover:bg-emerald-500/20" title="Renew user access for 1 year">
                                                    Renew
                                                </button>
                                            </form>
                                        @endif

                                        @if($user->status === 'pending')
                                            <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 border border-green-500/20 text-xs uppercase tracking-[0.3em] hover:bg-green-500/20">
                                                    Approve
                                                </button>
                                            </form>
                                        @elseif($user->status === 'active' && auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-2 rounded-lg bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 text-xs uppercase tracking-[0.3em] hover:bg-yellow-500/20">
                                                    Suspend
                                                </button>
                                            </form>
                                        @elseif($user->status === 'suspended')
                                            <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 border border-green-500/20 text-xs uppercase tracking-[0.3em] hover:bg-green-500/20">
                                                    Reactivate
                                                </button>
                                            </form>
                                        @endif

                                        <button 
                                            type="button" 
                                            @click="$dispatch('open-permissions-modal', { user: {{ json_encode($user->only(['id', 'name', 'job_level', 'permissions_matrix'])) }} })"
                                            class="px-3 py-2 rounded-lg bg-brand-white/10 text-brand-white/70 border border-brand-white/20 text-xs uppercase tracking-[0.3em] hover:bg-brand-white/20"
                                        >
                                            Privileges
                                        </button>
                                        
                                        @if($canArchiveUsers && auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to archive this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-2 rounded-lg text-brand-white/40 hover:text-brand-red hover:bg-brand-red/10 transition-colors" title="Archive User">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->access_role === 'super_admin' ? 11 : 10 }}" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="p-4 rounded-full bg-brand-white/5 text-brand-white/20">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm text-brand-white/60">No users found. Time to build the team!</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-xs text-brand-white/50">Passwords are encrypted. Use Reset Credentials to issue a new temporary password.</p>

        @if ($users instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- Permissions Matrix Modal -->
    <div
        x-data="{
            show: false,
            name: '',
            jobLevel: 'executive',
            permissions: [],
            actionUrl: '',
            availablePermissions: [
                { key: 'manage_tasks', label: 'Manage Tasks & Reassignment' },
                { key: 'manage_announcements', label: 'Manage Announcements' },
                { key: 'manage_payroll', label: 'Manage Payroll & Banking' },
                { key: 'manage_vendors', label: 'Manage Third-Party Vendors' },
                { key: 'manage_appraisals', label: 'Manage Appraisals Builder' },
                { key: 'manage_leaves', label: 'Manage Leave Approvals' },
                { key: 'manage_visitors', label: 'Manage Visitor Logs' }
            ],
            openModal(user) {
                this.name = user.name;
                this.jobLevel = user.job_level || 'executive';
                this.permissions = user.permissions_matrix || [];
                this.actionUrl = '/admin/users/' + user.id + '/permissions';
                this.show = true;
            }
        }"
        @open-permissions-modal.window="openModal($event.detail.user)"
        x-show="show"
        class="fixed inset-0 z-[999] flex items-center justify-center p-4 backdrop-blur-sm bg-brand-black/80"
        style="display: none;"
    >
        <div
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="show = false"
            class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-brand-white/10 bg-brand-black p-6 shadow-2xl space-y-6"
        >
            <div>
                <h3 class="text-xl font-semibold text-brand-white">Permissions Matrix</h3>
                <p class="mt-1 text-xs text-brand-white/50">Configure job hierarchy and access privileges for <span class="text-brand-white font-medium" x-text="name"></span></p>
            </div>

            <form method="POST" :action="actionUrl" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-xs uppercase tracking-[0.3em] text-brand-white/60 mb-2">Job Level Hierarchy</label>
                    <select name="job_level" x-model="jobLevel" class="w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2.5 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red">
                        <option value="super_admin">Super Admin</option>
                        <option value="manager">Line Manager</option>
                        <option value="executive">Standard Executive</option>
                        <option value="promoter">Freelance Promoter / Field Staff</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-[0.3em] text-brand-white/60 mb-3">Explicit Permission Checklist</label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <template x-for="perm in availablePermissions" :key="perm.key">
                            <label class="flex items-start gap-2.5 rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 cursor-pointer hover:bg-brand-white/10 transition-colors">
                                <input type="checkbox" name="permissions[]" :value="perm.key" :checked="permissions.includes(perm.key)" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-0 mt-0.5">
                                <span class="text-xs text-brand-white/70" x-text="perm.label"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-white/10">
                    <button
                        type="button"
                        @click="show = false"
                        class="rounded-full border border-brand-white/20 px-5 py-2 text-xs uppercase tracking-[0.2em] text-brand-white/70 hover:bg-brand-white/5 hover:text-brand-white"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-full bg-brand-red px-5 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
