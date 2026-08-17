<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">CMIH Hub</p>
            <h2 class="text-3xl font-display text-brand-white">Team Management</h2>
        </div>
    </x-slot>

    <div x-data="{ 
        activeTab: 'directory',
        openModal: false,
        targetUser: {},
        actionUrl: '',
        editPrivileges(user) {
            this.targetUser = Object.assign({}, user);
            this.actionUrl = '{{ url('/portal/directory') }}/' + user.id + '/privileges';
            this.openModal = true;
        }
    }" class="space-y-6">
        
        <!-- Tab Selectors -->
        <div class="flex border-b border-brand-white/10 space-x-4">
            <button @click="activeTab = 'directory'" :class="activeTab === 'directory' ? 'border-amber-500 text-amber-500 font-semibold' : 'border-transparent text-brand-white/60 hover:text-brand-white'" class="py-3 px-4 border-b-2 text-sm uppercase tracking-wider transition-colors focus:outline-none">
                📋 Staff Directory & Privileges
            </button>
            <button @click="activeTab = 'organogram'" :class="activeTab === 'organogram' ? 'border-amber-500 text-amber-500 font-semibold' : 'border-transparent text-brand-white/60 hover:text-brand-white'" class="py-3 px-4 border-b-2 text-sm uppercase tracking-wider transition-colors focus:outline-none">
                📊 Company Organogram
            </button>
        </div>

        @if (session('status'))
            <div class="glass-panel border-l-4 border-emerald-500 rounded-xl p-4 text-sm text-emerald-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="glass-panel border-l-4 border-brand-red rounded-xl p-4 text-sm text-brand-red">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Tab 1: Staff Directory & Privileges -->
        <div x-show="activeTab === 'directory'" class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <div class="glass-panel rounded-2xl p-6 overflow-hidden">
                <h3 class="text-lg font-semibold text-brand-white mb-4">Active Staff Members</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-brand-white border-collapse">
                        <thead>
                            <tr class="border-b border-brand-white/10 text-xs uppercase tracking-wider text-brand-ash">
                                <th class="py-3 px-4">Staff Member</th>
                                <th class="py-3 px-4">Department</th>
                                <th class="py-3 px-4">Job Level</th>
                                <th class="py-3 px-4">Access Role</th>
                                <th class="py-3 px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 text-sm">
                            @forelse ($team as $member)
                                <tr>
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $member->profilePhotoUrl() }}" alt="{{ $member->name }}" class="h-10 w-10 rounded-full object-cover border border-brand-white/20" />
                                            <div>
                                                <p class="font-medium text-brand-white">{{ $member->name }}</p>
                                                <p class="text-xs text-brand-white/50">{{ $member->email }}</p>
                                                <p class="text-[10px] text-brand-white/40">{{ $member->phone ?? 'No Phone' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 uppercase tracking-wider text-xs text-brand-white/70">
                                        {{ str_replace('_', ' ', $member->department ?? 'N/A') }}
                                    </td>
                                    <td class="py-4 px-4 text-xs text-amber-500 font-semibold">
                                        {{ $member->position_title ?? 'Team Member' }}
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] uppercase font-bold tracking-wider 
                                            {{ $member->access_role === 'super_admin' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : '' }}
                                            {{ $member->access_role === 'admin' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : '' }}
                                            {{ $member->access_role === 'manager' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : '' }}
                                            {{ $member->access_role === 'staff' ? 'bg-brand-white/10 text-brand-white/60' : '' }}
                                        ">
                                            {{ $member->access_role }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        @if (\App\Models\User::canEditUser(auth()->user(), $member))
                                            @php
                                                $viewer = auth()->user();
                                                $isHrOrSuperOrCvo = $viewer->hasFullHrAccess();
                                                
                                                $memberData = [
                                                    'id' => $member->id,
                                                    'name' => $member->name,
                                                    'access_role' => $member->access_role,
                                                    'position_title' => $member->position_title,
                                                    'department' => $member->department,
                                                ];

                                                if ($isHrOrSuperOrCvo) {
                                                    $memberData['salary'] = $member->salary;
                                                    $memberData['payroll_deductions'] = $member->payroll_deductions;
                                                    $memberData['payroll_rewards_bonus'] = $member->payroll_rewards_bonus;
                                                    $memberData['payroll_notes'] = $member->payroll_notes;
                                                    $memberData['contract_path'] = $member->contract_path;
                                                    $memberData['job_description_path'] = $member->job_description_path;
                                                    $memberData['contract_url'] = $member->contract_path ? route('portal.payroll.document', [$member, 'contract']) : null;
                                                    $memberData['job_description_url'] = $member->job_description_path ? route('portal.payroll.document', [$member, 'job-description']) : null;
                                                }
                                            @endphp
                                            <button @click="editPrivileges({{ json_encode($memberData) }})" class="px-3 py-1 rounded bg-amber-500/20 text-amber-400 hover:bg-amber-500/30 text-xs transition border border-amber-500/20">
                                                Edit Privileges
                                            </button>
                                        @else
                                            <span class="text-xs text-brand-white/30 italic">No Edit Rights</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 px-4 text-sm text-brand-white/60 text-center">
                                        No active staff profiles yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($team instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="pt-4">
                        {{ $team->links() }}
                    </div>
                @endif
            </div>

            <!-- Birthdays & Anniversaries Side Panels -->
            <aside class="space-y-6">
                @if (auth()->user()->access_role === 'super_admin')
                    <div class="glass-panel rounded-2xl p-6">
                        <h3 class="text-lg font-semibold text-brand-white">Upcoming Birthdays</h3>
                        <p class="mt-1 text-xs text-brand-white/45">Showing birthdays in the next 30 days.</p>
                        <div class="mt-4 space-y-3">
                            @forelse ($birthdays as $person)
                                <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3">
                                    <p class="text-sm text-brand-white font-medium">{{ $person->name }}</p>
                                    <p class="text-xs text-brand-white/60">{{ $person->birthday_day }} / {{ Carbon\Carbon::create()->month($person->birthday_month)->format('F') }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-brand-white/60">No birthdays in the next 30 days.</p>
                            @endforelse
                        </div>
                    </div>
                @endif

                <div class="glass-panel rounded-2xl p-6">
                    <h3 class="text-lg font-semibold text-brand-white">Work Anniversaries</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($anniversaries as $person)
                            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-3">
                                <p class="text-sm text-brand-white font-medium">{{ $person->name }}</p>
                                <p class="text-xs text-brand-white/60">{{ $person->start_date?->format('M d, Y') }} (Joined)</p>
                            </div>
                        @empty
                            <p class="text-sm text-brand-white/60">No anniversaries this month.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>

        <!-- Tab 2: Company Organogram Pyramid -->
        <div x-show="activeTab === 'organogram'" x-data="{ 
            activeTier: 'CVO',
            users: {{ json_encode($organogramUsers) }}
        }" class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]" style="display: none;">
            
            <!-- SVG Organogram Pyramid Column -->
            <div class="glass-panel rounded-2xl p-6 flex flex-col items-center justify-center bg-brand-black/40">
                <div class="text-center mb-6">
                    <h3 class="text-lg font-semibold text-brand-white">Interactive Organogram Pyramid</h3>
                    <p class="text-xs text-brand-ash mt-1">Click a tier to filter matching staff members below the organization tree.</p>
                </div>

                <!-- SVG Stack representing levels -->
                <svg viewBox="0 0 500 450" class="w-full max-w-lg filter drop-shadow-2xl">
                    <defs>
                        <!-- Glow Filters -->
                        <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="5" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                    </defs>

                    <!-- Network Connections Grid -->
                    <g opacity="0.15" stroke="#fbbf24" stroke-width="1">
                        <!-- Diagonal Mesh Lines -->
                        <line x1="250" y1="20" x2="110" y2="400" />
                        <line x1="250" y1="20" x2="390" y2="400" />
                        <line x1="250" y1="20" x2="250" y2="400" />
                        
                        <!-- Horizontal connector reference lines -->
                        <line x1="200" y1="70" x2="300" y2="70" />
                        <line x1="185" y1="125" x2="315" y2="125" />
                        <line x1="170" y1="180" x2="330" y2="180" />
                        <line x1="155" y1="235" x2="345" y2="235" />
                        <line x1="140" y1="290" x2="360" y2="290" />
                        <line x1="125" y1="345" x2="375" y2="345" />
                    </g>

                    <!-- Central Trunk Reporting Line -->
                    <line x1="250" y1="45" x2="250" y2="375" stroke="#fbbf24" stroke-width="1.5" stroke-dasharray="4,4" opacity="0.5" />

                    <!-- Tier 1: CVO -->
                    <polygon points="215,20 285,20 300,70 200,70" 
                        class="cursor-pointer transition-all duration-300 hover:fill-amber-400"
                        :fill="activeTier === 'CVO' ? 'rgba(251,191,36,0.95)' : 'rgba(251,191,36,0.65)'"
                        :stroke="activeTier === 'CVO' ? '#fbbf24' : 'rgba(251,191,36,0.3)'"
                        :stroke-width="activeTier === 'CVO' ? '2.5' : '1'"
                        filter="activeTier === 'CVO' ? 'url(#glow)' : ''"
                        @click="activeTier = 'CVO'" />
                    <text x="250" y="50" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" class="pointer-events-none select-none font-sans tracking-widest uppercase">CVO</text>

                    <!-- Tier 2: Department Head -->
                    <polygon points="200,75 300,75 315,125 185,125" 
                        class="cursor-pointer transition-all duration-300 hover:fill-amber-500"
                        :fill="activeTier === 'Department Head' ? 'rgba(245,158,11,0.9)' : 'rgba(245,158,11,0.65)'"
                        :stroke="activeTier === 'Department Head' ? '#f59e0b' : 'rgba(245,158,11,0.3)'"
                        :stroke-width="activeTier === 'Department Head' ? '2.5' : '1'"
                        filter="activeTier === 'Department Head' ? 'url(#glow)' : ''"
                        @click="activeTier = 'Department Head'" />
                    <text x="250" y="105" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" class="pointer-events-none select-none font-sans tracking-wider uppercase">Department Head</text>

                    <!-- Tier 3: Manager -->
                    <polygon points="185,130 315,130 330,180 170,180" 
                        class="cursor-pointer transition-all duration-300 hover:fill-amber-600"
                        :fill="activeTier === 'Manager' ? 'rgba(217,119,6,0.85)' : 'rgba(217,119,6,0.6)'"
                        :stroke="activeTier === 'Manager' ? '#d97706' : 'rgba(217,119,6,0.3)'"
                        :stroke-width="activeTier === 'Manager' ? '2.5' : '1'"
                        filter="activeTier === 'Manager' ? 'url(#glow)' : ''"
                        @click="activeTier = 'Manager'" />
                    <text x="250" y="160" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" class="pointer-events-none select-none font-sans tracking-wider uppercase">Manager</text>

                    <!-- Tier 4: Assistant Manager -->
                    <polygon points="170,185 330,185 345,235 155,235" 
                        class="cursor-pointer transition-all duration-300 hover:fill-amber-700"
                        :fill="activeTier === 'Assistant Manager' ? 'rgba(180,83,9,0.8)' : 'rgba(180,83,9,0.55)'"
                        :stroke="activeTier === 'Assistant Manager' ? '#b45309' : 'rgba(180,83,9,0.3)'"
                        :stroke-width="activeTier === 'Assistant Manager' ? '2.5' : '1'"
                        filter="activeTier === 'Assistant Manager' ? 'url(#glow)' : ''"
                        @click="activeTier = 'Assistant Manager'" />
                    <text x="250" y="215" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" class="pointer-events-none select-none font-sans tracking-wider uppercase">Assistant Manager</text>

                    <!-- Tier 5: Senior Executive -->
                    <polygon points="155,240 345,240 360,290 140,290" 
                        class="cursor-pointer transition-all duration-300 hover:fill-amber-800"
                        :fill="activeTier === 'Senior Executive' ? 'rgba(146,64,14,0.75)' : 'rgba(146,64,14,0.5)'"
                        :stroke="activeTier === 'Senior Executive' ? '#92400e' : 'rgba(146,64,14,0.3)'"
                        :stroke-width="activeTier === 'Senior Executive' ? '2.5' : '1'"
                        filter="activeTier === 'Senior Executive' ? 'url(#glow)' : ''"
                        @click="activeTier = 'Senior Executive'" />
                    <text x="250" y="270" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" class="pointer-events-none select-none font-sans tracking-wider uppercase">Senior Executive</text>

                    <!-- Tier 6: Executive -->
                    <polygon points="140,295 360,295 375,345 125,345" 
                        class="cursor-pointer transition-all duration-300 hover:fill-amber-900"
                        :fill="activeTier === 'Executive' ? 'rgba(120,53,15,0.7)' : 'rgba(120,53,15,0.45)'"
                        :stroke="activeTier === 'Executive' ? '#78350f' : 'rgba(120,53,15,0.3)'"
                        :stroke-width="activeTier === 'Executive' ? '2.5' : '1'"
                        filter="activeTier === 'Executive' ? 'url(#glow)' : ''"
                        @click="activeTier = 'Executive'" />
                    <text x="250" y="325" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" class="pointer-events-none select-none font-sans tracking-wider uppercase">Executive</text>

                    <!-- Tier 7: Support Staff -->
                    <polygon points="125,350 375,350 390,400 110,400" 
                        class="cursor-pointer transition-all duration-300 hover:fill-[#6e1e06]"
                        :fill="activeTier === 'Support Staff' ? 'rgba(69,26,3,0.7)' : 'rgba(69,26,3,0.4)'"
                        :stroke="activeTier === 'Support Staff' ? '#451a03' : 'rgba(69,26,3,0.3)'"
                        :stroke-width="activeTier === 'Support Staff' ? '2.5' : '1'"
                        filter="activeTier === 'Support Staff' ? 'url(#glow)' : ''"
                        @click="activeTier = 'Support Staff'" />
                    <text x="250" y="380" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" class="pointer-events-none select-none font-sans tracking-wider uppercase">Support Staff</text>

                    <!-- Network node circles -->
                    <circle cx="250" cy="45" r="4" fill="#fff" class="pointer-events-none" />
                    <circle cx="250" cy="100" r="4" fill="#fff" class="pointer-events-none" />
                    <circle cx="250" cy="155" r="4" fill="#fff" class="pointer-events-none" />
                    <circle cx="250" cy="210" r="4" fill="#fff" class="pointer-events-none" />
                    <circle cx="250" cy="265" r="4" fill="#fff" class="pointer-events-none" />
                    <circle cx="250" cy="320" r="4" fill="#fff" class="pointer-events-none" />
                    <circle cx="250" cy="375" r="4" fill="#fff" class="pointer-events-none" />
                </svg>
            </div>

            <!-- Tier Details Panel Column -->
            <div class="glass-panel rounded-2xl p-6 flex flex-col bg-brand-black/20">
                <div class="border-b border-brand-white/10 pb-4 mb-4">
                    <h3 class="text-xl font-display text-brand-white">
                        Tier Directory: <span class="text-amber-400 font-semibold" x-text="activeTier"></span>
                    </h3>
                    <p class="text-xs text-brand-ash mt-1">Listing all active staff members positioned at this level.</p>
                </div>

                <div class="space-y-3 max-h-[450px] overflow-y-auto pr-2">
                    <!-- Iterating active tier members -->
                    <template x-for="u in users.filter(user => user.position_title === activeTier)">
                        <div class="flex items-center gap-4 p-3 rounded-xl bg-brand-white/5 border border-brand-white/10 hover:border-amber-500/30 transition-all">
                            <img :src="u.profile_photo_url"
                                class="w-12 h-12 rounded-full object-cover border border-brand-white/10" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-brand-white truncate" x-text="u.name"></p>
                                <p class="text-xs uppercase tracking-wider text-amber-500/80 font-mono font-bold" x-text="u.position_title || 'Team Member'"></p>
                                <p class="text-[11px] text-brand-white/60 truncate" x-text="u.department ? u.department.replace('_', ' ').toUpperCase() : 'NO DEPARTMENT'"></p>
                            </div>
                            <div>
                                <span class="px-2 py-0.5 rounded bg-brand-white/10 text-brand-white/70 text-[9px] uppercase tracking-wider font-semibold" x-text="u.access_role"></span>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div x-show="users.filter(user => user.position_title === activeTier).length === 0" class="text-center py-12">
                        <div class="text-4xl mb-2">👥</div>
                        <p class="text-sm text-brand-white/40 italic">No staff members allocated to this job level.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alpine.js Edit Privileges Modal -->
        <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-black/80 backdrop-blur-sm" x-cloak style="display: none;">
            <div @click.away="openModal = false" class="w-full max-w-md glass-panel rounded-2xl p-6 border border-brand-white/15 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <button @click="openModal = false" class="absolute top-4 right-4 text-brand-white/60 hover:text-brand-white text-lg transition-colors focus:outline-none">
                    ✕
                </button>
                
                <h3 class="text-lg font-semibold text-brand-white mb-2">Edit Staff Privileges</h3>
                <p class="text-xs text-brand-ash mb-6">Modify system access role, position level, and department for <span class="text-amber-400 font-semibold" x-text="targetUser.name"></span> immediately.</p>
                
                <form :action="actionUrl" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    
                    <!-- Access Role -->
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Access Role</label>
                        <select name="access_role" x-model="targetUser.access_role" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm">
                            <option value="staff">Staff (Executive)</option>
                            <option value="manager">Manager (Line Manager)</option>
                            @if (auth()->user()->hasRole('super_admin'))
                                <option value="admin">HR Admin (Admin)</option>
                                <option value="super_admin">Super Admin</option>
                            @endif
                        </select>
                    </div>

                    <!-- Job Level (Position Title) -->
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Job level (Position Title)</label>
                        <select name="position_title" x-model="targetUser.position_title" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm">
                            <option value="Support Staff">Support Staff</option>
                            <option value="Executive">Executive</option>
                            <option value="Senior Executive">Senior Executive</option>
                            <option value="Assistant Manager">Assistant Manager</option>
                            <option value="Manager">Manager</option>
                            <option value="Department Head">Department Head</option>
                            <option value="CVO">CVO</option>
                        </select>
                    </div>

                    <!-- Department -->
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Department</label>
                        <select name="department" x-model="targetUser.department" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm">
                            <option value="hr_admin">HR & Admin</option>
                            <option value="finance">Finance</option>
                            <option value="client_relations">Client Relations</option>
                            <option value="operations_projects">Operations & Projects</option>
                            <option value="brands_marketing">Brands & Marketing</option>
                            <option value="creatives">Creatives</option>
                        </select>
                    </div>

                    @php
                        $viewer = auth()->user();
                        $isHrOrSuperOrCvo = $viewer->hasFullHrAccess();
                    @endphp

                    @if ($isHrOrSuperOrCvo)
                        <!-- Salary -->
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Monthly Salary (GHS)</label>
                            <input type="number" step="0.01" name="salary" x-model="targetUser.salary" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" placeholder="e.g. 5000.00" />
                        </div>

                        <div>
                            <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Deductions (GHS)</label>
                            <input type="number" step="0.01" name="payroll_deductions" x-model="targetUser.payroll_deductions" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" placeholder="Optional" />
                        </div>

                        <div>
                            <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Rewards / Bonus (GHS)</label>
                            <input type="number" step="0.01" name="payroll_rewards_bonus" x-model="targetUser.payroll_rewards_bonus" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" placeholder="Optional" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Payroll Notes</label>
                            <textarea name="payroll_notes" x-model="targetUser.payroll_notes" rows="3" class="w-full rounded-xl border border-brand-white/10 bg-brand-black text-brand-white px-4 py-2.5 focus:border-amber-500 focus:outline-none text-sm" placeholder="Optional payroll note"></textarea>
                        </div>

                        <!-- Contract -->
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Contract File (.pdf, .doc, .docx, .jpg, .png)</label>
                            <input type="file" name="contract" class="w-full text-xs text-brand-white bg-brand-black/50 border border-brand-white/10 rounded-xl px-4 py-2 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-500 file:text-brand-black hover:file:bg-amber-400" />
                            <template x-if="targetUser.contract_path">
                                <div class="mt-2 text-xs flex items-center gap-1.5">
                                    <span class="text-brand-ash">📄 Current Contract:</span>
                                    <a :href="targetUser.contract_url || '#'" target="_blank" class="text-amber-400 hover:underline font-semibold">Download / View</a>
                                </div>
                            </template>
                        </div>

                        <!-- Job Description -->
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-brand-ash mb-2">Job Description File (.pdf, .doc, .docx, .jpg, .png)</label>
                            <input type="file" name="job_description" class="w-full text-xs text-brand-white bg-brand-black/50 border border-brand-white/10 rounded-xl px-4 py-2 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-500 file:text-brand-black hover:file:bg-amber-400" />
                            <template x-if="targetUser.job_description_path">
                                <div class="mt-2 text-xs flex items-center gap-1.5">
                                    <span class="text-brand-ash">📄 Current Job Description:</span>
                                    <a :href="targetUser.job_description_url || '#'" target="_blank" class="text-amber-400 hover:underline font-semibold">Download / View</a>
                                </div>
                            </template>
                        </div>
                    @endif

                    <!-- Action buttons -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-white/10">
                        <button type="button" @click="openModal = false" class="px-4 py-2 rounded-xl text-xs uppercase tracking-wider text-brand-white/60 hover:text-brand-white bg-brand-white/5 hover:bg-brand-white/10 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider text-brand-black bg-amber-500 hover:bg-amber-400 transition shadow-lg shadow-amber-500/20">
                            Apply Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
