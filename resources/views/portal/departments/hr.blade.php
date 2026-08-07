<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Departments</p>
            <h2 class="text-3xl font-display text-brand-white">HR & Admin Command Center</h2>
        </div>
    </x-slot>

    @php
        // Only HR Managers (Level 1) and Super Admin can access sensitive HR data
        $canDoSensitiveHr = auth()->user()->hasFullHrAccess();
        $canManageHrAnnouncements = auth()->user()->canManageHrAnnouncements();
    @endphp

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-400">
            {{ session('status') }}
        </div>
    @endif

    @if(auth()->user()->canReviewIdentityDocuments())
        @include('portal.partials.identity-document-register', ['identityDocuments' => $identityDocuments])
    @endif

    <div class="space-y-8">
        @if($canManageHrAnnouncements)
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Staff Communication</p>
                    <h3 class="text-lg font-display text-brand-white uppercase">HR Announcement Blast</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Send a notice to all internal staff, selected departments, or specific staff members. Each recipient receives a portal notification.</p>
                </div>
                <a href="{{ route('portal.announcements') }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white hover:bg-brand-white/10">
                    View Announcements
                </a>
            </div>

            <form method="POST" action="{{ route('portal.hr.announcements.store') }}" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.5fr)]">
                @csrf
                <div class="space-y-4">
                    <div>
                        <x-input-label for="announcement_title" :value="__('Announcement Title')" />
                        <x-text-input id="announcement_title" name="title" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Mandatory staff briefing" />
                    </div>
                    <div>
                        <x-input-label for="announcement_body" :value="__('Message')" />
                        <textarea id="announcement_body" name="body" rows="6" required class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Type the announcement here..."></textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs text-brand-white/70">
                        <input type="checkbox" name="pinned" value="1" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red">
                        Pin this announcement
                    </label>
                </div>

                <div class="space-y-4 rounded-2xl border border-brand-white/10 bg-brand-black/30 p-4">
                    <div>
                        <x-input-label for="audience_type" :value="__('Recipients')" />
                        <select id="audience_type" name="audience_type" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" onchange="toggleHrAnnouncementAudience(this.value)">
                            <option value="all">All internal staff</option>
                            <option value="departments">Selected departments</option>
                            <option value="selected">Selected staff</option>
                        </select>
                    </div>

                    <div id="announcement_departments" class="hidden">
                        <x-input-label :value="__('Departments')" />
                        <div class="mt-2 grid gap-2 text-xs text-brand-white/70">
                            @foreach([
                                'hr_admin' => 'HR & Admin',
                                'finance' => 'Finance',
                                'client_relations' => 'Client Relations',
                                'operations_projects' => 'Operations',
                                'brands_marketing' => 'Brands & Marketing',
                                'creatives' => 'Creatives',
                            ] as $key => $label)
                                <label class="flex items-center gap-2 rounded-lg border border-brand-white/10 bg-brand-white/[0.03] px-3 py-2">
                                    <input type="checkbox" name="department_keys[]" value="{{ $key }}" class="rounded border-brand-white/20 bg-brand-black/40 text-brand-red focus:ring-brand-red">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div id="announcement_staff" class="hidden">
                        <x-input-label for="recipient_ids" :value="__('Staff Members')" />
                        <select id="recipient_ids" name="recipient_ids[]" multiple size="8" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }} - {{ $member->department ?? 'No department' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-3 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Send Announcement
                    </button>
                </div>
            </form>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                @forelse($recentAnnouncements as $announcement)
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-brand-white">{{ $announcement->title }}</p>
                            <span class="rounded-full border border-brand-white/10 bg-brand-black/30 px-2 py-0.5 text-[9px] uppercase text-brand-white/50">{{ $announcement->audience_type ?? 'all' }}</span>
                        </div>
                        <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-brand-white/55">{!! nl2br(e($announcement->plainBody(220))) !!}</p>
                        <p class="mt-3 text-[10px] text-brand-ash">By {{ $announcement->user?->name ?? 'HR Admin' }} on {{ $announcement->created_at->format('M d, Y') }}</p>
                    </div>
                @empty
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/[0.04] p-4 text-xs text-brand-white/50 md:col-span-3">
                        No staff announcements have been published yet.
                    </div>
                @endforelse
            </div>
        </div>
        @endif

        <!-- Grid layout: Visitor Log & LifeCycle -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Visitor Log Form and Listing -->
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">🚪 Visitor Management System</h3>
                <form method="POST" action="{{ route('portal.hr.visitors.store') }}" class="space-y-4 mb-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="visitor_name" :value="__('Visitor Name')" />
                            <x-text-input id="visitor_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="John Doe" />
                        </div>
                        <div>
                            <x-input-label for="visitor_company" :value="__('Company / Agency')" />
                            <x-text-input id="visitor_company" name="company" type="text" class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Google Inc." />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="visitor_purpose" :value="__('Purpose of Visit')" />
                        <x-text-input id="visitor_purpose" name="purpose" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="Pitch meeting / Strategy review" />
                    </div>
                    <div>
                        <x-input-label for="host_id" :value="__('Host / Staff Met')" />
                        <select id="host_id" name="host_id" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="">Select Host</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-brand-red hover:bg-brand-red-dark px-6 py-2 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                            Log Check-In
                        </button>
                    </div>
                </form>

                <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3 border-t border-brand-white/10 pt-4">Recent Visits</h4>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    @forelse($visitors as $visitor)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 flex justify-between items-center text-xs">
                            <div>
                                <p class="font-semibold text-brand-white">{{ $visitor->name }} <span class="text-brand-white/40">from {{ $visitor->company ?? 'N/A' }}</span></p>
                                <p class="text-brand-white/60">Meeting: {{ $visitor->purpose }}</p>
                                <p class="text-brand-ash text-[10px]">Host: {{ $visitor->host?->name ?? 'Staff' }}</p>
                            </div>
                            <div class="text-right">
                                @if($visitor->status === 'checked_in')
                                    <form method="POST" action="{{ route('portal.hr.visitors.checkout', $visitor) }}">
                                        @csrf
                                        <button type="submit" class="rounded-full bg-brand-red px-3 py-1 text-[10px] text-brand-white uppercase font-bold hover:bg-brand-red-dark transition-all">
                                            Check Out
                                        </button>
                                    </form>
                                @else
                                    <span class="text-emerald-400 font-semibold uppercase text-[10px]">Out ({{ optional($visitor->time_out)->format('H:i') }})</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/40 italic text-center py-4">No visitor logs recorded today.</p>
                    @endforelse
                </div>
                @if($visitors instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $visitors->links() }}
                    </div>
                @endif
            </div>

            {{-- Employee Lifecycle Tracker: HR Manager only --}}
            @if($canDoSensitiveHr)
            <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📊 Employee Lifecycle & Contracts</h3>
                <div class="space-y-4">
                    @foreach($staff->take(5) as $employee)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $employee->profilePhotoUrl() }}" alt="{{ $employee->name }}" class="h-10 w-10 rounded-full object-cover">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-white">{{ $employee->name }}</p>
                                        <p class="text-xs text-brand-white/50">{{ ucfirst($employee->department ?? 'Operations') }} · {{ $employee->job_title ?? 'Staff' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3 py-0.5 text-[10px] uppercase font-bold text-emerald-400">
                                        Contract: Active
                                    </span>
                                    <p class="text-[10px] text-brand-ash mt-1">Expiry: {{ $employee->id_card_expires_at?->format('M Y') ?? 'Dec 2027' }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif{{-- /canDoSensitiveHr --}}
        </div>{{-- /Visitor+Lifecycle grid --}}

        @if($canDoSensitiveHr)
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">Leave Entitlements & HR Exports</h3>
                    <p class="mt-1 text-xs text-brand-white/50">Set each staff member's remaining leave days. Approved leave requests deduct from this balance automatically.</p>
                </div>
                <a href="{{ route('portal.export', ['table' => 'assets']) }}" class="rounded-xl border border-brand-white/10 bg-brand-white/5 px-3.5 py-2 text-xs font-semibold uppercase tracking-wider text-brand-white transition hover:bg-brand-white/10">
                    Export Asset Inventory
                </a>
            </div>
            <div class="overflow-x-auto rounded-xl border border-brand-white/10">
                <table class="w-full min-w-[760px] text-left text-xs text-brand-white/70">
                    <thead class="bg-brand-black/40 text-[10px] uppercase tracking-widest text-brand-ash">
                        <tr>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Current Balance</th>
                            <th class="px-4 py-3 text-right">HR Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-white/5">
                        @foreach($staff as $member)
                            <tr class="align-middle hover:bg-brand-white/[0.02]">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-brand-white">{{ $member->name }}</p>
                                    <p class="text-[10px] text-brand-white/35">{{ $member->email }}</p>
                                </td>
                                <td class="px-4 py-3">{{ \App\Models\User::departmentLabel($member->department) }}</td>
                                <td class="px-4 py-3 font-mono text-brand-white">{{ (int) $member->leave_balance }} day(s)</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('portal.hr.leave-balance.update', $member) }}" class="ml-auto flex max-w-xs items-center justify-end gap-2">
                                        @csrf
                                        <input type="number" name="leave_balance" min="0" max="365" step="1" value="{{ old('leave_balance', $member->leave_balance) }}" class="w-24 rounded-lg border border-brand-white/10 bg-brand-black px-3 py-2 text-xs text-brand-white focus:border-brand-red focus:ring-0">
                                        <button type="submit" class="rounded-lg bg-brand-red px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-brand-red-dark">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ── Employee Lifecycle & Contracts: HR Manager only ──────────────── --}}
        @if($canDoSensitiveHr)
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white">🏆 Appraisal Form Builder</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('portal.import.show', ['table' => 'appraisal_metrics']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        📥 Import Metrics
                    </a>
                    <a href="{{ route('portal.export', ['table' => 'appraisal_metrics']) }}" class="rounded-xl bg-brand-white/5 border border-brand-white/10 hover:bg-brand-white/10 px-3.5 py-1.5 text-xs text-brand-white font-semibold transition uppercase tracking-wider">
                        📤 Export Metrics
                    </a>
                </div>
            </div>
            <div class="space-y-6">
                <form method="POST" action="{{ route('portal.hr.appraisals.metrics.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="metric_name" :value="__('Metric Name')" />
                        <x-text-input id="metric_name" name="name" type="text" required class="mt-1 w-full border border-brand-white/10 bg-brand-black/40 text-brand-white placeholder-brand-white/30" placeholder="e.g. Critical Thinking or punctuality" />
                    </div>
                    <div>
                        <x-input-label for="metric_category" :value="__('Category')" />
                        <select id="metric_category" name="category" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" required>
                            <option value="General">General Performance</option>
                            <option value="Technical">Technical Competency</option>
                            <option value="Leadership">Leadership & Teamwork</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="metric_type" :value="__('Metric Type')" />
                        <select id="metric_type" name="metric_type" onchange="toggleTableSettings(this.value)"
                            class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                            <option value="slider">1–10 Slider rating</option>
                            <option value="table">Target / Objective Grid (Table)</option>
                        </select>
                    </div>

                    {{-- Table Settings Panel --}}
                    <div id="table_settings_panel" class="hidden space-y-4 border border-brand-white/10 rounded-xl p-4 bg-brand-white/5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-brand-ash">Table Template Builder</h4>
                        <div>
                            <x-input-label for="default_rows" :value="__('Default Empty Rows')" />
                            <input id="default_rows" name="default_rows" type="number" min="1" max="15" value="3"
                                class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white" />
                        </div>
                        
                        <div>
                            <x-input-label :value="__('Columns Configuration')" />
                            <div id="columns_list" class="space-y-2 mt-2">
                                {{-- Columns will be rendered here dynamically --}}
                            </div>
                            <button type="button" onclick="addColumnRow()" class="mt-2 text-xs text-brand-red font-semibold hover:underline">
                                ＋ Add Column definition
                            </button>
                        </div>
                        
                        <input type="hidden" name="table_template" id="table_template_input" value="[]" />
                    </div>

                    <div>
                        <x-input-label for="metric_description" :value="__('Description')" />
                        <textarea id="metric_description" name="description" class="wysiwyg-editor mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30" placeholder="Describe the review criteria"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-[0.2em] font-semibold text-brand-white transition-all">
                        Add Metric Row
                    </button>
                </form>

                <div>
                    <h4 class="text-xs uppercase tracking-[0.15em] text-brand-ash mb-3">Active Assessment Parameters</h4>
                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2">
                        @foreach(['General' => 'General Performance', 'Technical' => 'Technical Competency', 'Leadership' => 'Leadership & Teamwork'] as $cat => $title)
                            @php
                                $catMetrics = $metrics->where('category', $cat);
                            @endphp
                            <div class="space-y-2">
                                <h5 class="text-[10px] font-bold uppercase tracking-wider text-brand-red border-b border-brand-white/10 pb-1">{{ $title }}</h5>
                                @forelse($catMetrics as $m)
                                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-3 flex justify-between items-center text-xs">
                                        <div>
                                            <p class="font-semibold text-brand-white">
                                                {{ $m->name }}
                                                @if($m->metric_type === 'table')
                                                    <span class="ml-2 rounded bg-amber-500/10 border border-amber-500/20 px-1.5 py-0.5 text-[9px] uppercase font-bold text-amber-400">Grid</span>
                                                @else
                                                    <span class="ml-2 rounded bg-brand-white/10 border border-brand-white/20 px-1.5 py-0.5 text-[9px] uppercase font-bold text-brand-white/50">Slider</span>
                                                @endif
                                            </p>
                                            @if($m->description)
                                                <p class="text-brand-white/60 text-[10px] mt-1">{!! $m->description !!}</p>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('portal.hr.appraisals.metrics.destroy', $m) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-brand-red hover:text-red-400 transition-colors">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                @empty
                                    <p class="text-[10px] text-brand-white/30 italic">No metrics listed in this section.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>

                <script>
                    let columns = [
                        { key: 'objective', label: 'Objective / KPI', type: 'text', width: '30%' },
                        { key: 'weight', label: 'Weight (%)', type: 'number', width: '10%' },
                        { key: 'target', label: 'Target', type: 'text', width: '20%' },
                        { key: 'actual', label: 'Actual', type: 'text', width: '20%' },
                        { key: 'score', label: 'Score (1–10)', type: 'score', width: '10%' },
                        { key: 'remarks', label: 'Remarks', type: 'textarea', width: '10%' }
                    ];

                    function toggleTableSettings(type) {
                        const panel = document.getElementById('table_settings_panel');
                        if (type === 'table') {
                            panel.classList.remove('hidden');
                            renderColumns();
                        } else {
                            panel.classList.add('hidden');
                        }
                    }

                    function renderColumns() {
                        const container = document.getElementById('columns_list');
                        container.innerHTML = '';
                        
                        columns.forEach((col, index) => {
                            const row = document.createElement('div');
                            row.className = 'flex items-center gap-2 border-b border-brand-white/5 pb-2 text-xs';
                            row.innerHTML = `
                                <input type="text" placeholder="Key" value="${col.key}" onchange="updateCol(${index}, 'key', this.value)"
                                    class="w-1/4 rounded border border-brand-white/10 bg-brand-black/60 px-2 py-1 text-xs text-brand-white" required />
                                <input type="text" placeholder="Label" value="${col.label}" onchange="updateCol(${index}, 'label', this.value)"
                                    class="w-1/3 rounded border border-brand-white/10 bg-brand-black/60 px-2 py-1 text-xs text-brand-white" required />
                                <select onchange="updateCol(${index}, 'type', this.value)"
                                    class="w-1/4 rounded border border-brand-white/10 bg-brand-black/60 px-1 py-1 text-xs text-brand-white">
                                    <option value="text" ${col.type === 'text' ? 'selected' : ''}>Text</option>
                                    <option value="number" ${col.type === 'number' ? 'selected' : ''}>Number</option>
                                    <option value="score" ${col.type === 'score' ? 'selected' : ''}>Score</option>
                                    <option value="textarea" ${col.type === 'textarea' ? 'selected' : ''}>Textarea</option>
                                </select>
                                <input type="text" placeholder="Width" value="${col.width}" onchange="updateCol(${index}, 'width', this.value)"
                                    class="w-16 rounded border border-brand-white/10 bg-brand-black/60 px-1 py-1 text-xs text-brand-white" />
                                <button type="button" onclick="deleteCol(${index})" class="text-brand-red font-bold hover:text-red-400">✕</button>
                            `;
                            container.appendChild(row);
                        });
                        
                        document.getElementById('table_template_input').value = JSON.stringify(columns);
                    }

                    function updateCol(index, key, val) {
                        columns[index][key] = val;
                        document.getElementById('table_template_input').value = JSON.stringify(columns);
                    }

                    function addColumnRow() {
                        columns.push({ key: '', label: '', type: 'text', width: '15%' });
                        renderColumns();
                    }

                    function deleteCol(index) {
                        columns.splice(index, 1);
                        renderColumns();
                    }
                </script>
            </div>
        </div>{{-- /Appraisal Form Builder --}}

        {{-- ── Central Vault: HR Manager only ─────────────────────────────── --}}
        <div class="glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-brand-white mb-4">📂 Central Vault (Templates & Guides)</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach([
                    ['name' => 'Onboarding Checklist Blueprint.docx', 'size' => '245 KB'],
                    ['name' => 'Employment Agreement Template.pdf', 'size' => '1.2 MB'],
                    ['name' => 'Performance Evaluation Manual.pdf', 'size' => '850 KB']
                ] as $doc)
                    <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <svg class="h-8 w-8 text-brand-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <div>
                                <p class="text-xs font-semibold text-brand-white truncate max-w-[150px]">{{ $doc['name'] }}</p>
                                <p class="text-[10px] text-brand-white/40">{{ $doc['size'] }}</p>
                            </div>
                        </div>
                        <a href="#" class="rounded-full bg-brand-white/10 p-2 text-brand-white hover:bg-brand-white/20 transition-all">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>{{-- /Central Vault --}}
        @endif{{-- /canDoSensitiveHr --}}
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         PHASE 3 PANELS: Visitor Pre-Ticketing & Phone/Vendor Directory
    ══════════════════════════════════════════════════════════════════════ --}}

    {{-- Visitor Pre-Ticketing ──────────────────────────────────────────────── --}}
    <div class="mt-8 glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Phase 3</p>
                <h3 class="text-lg font-display text-brand-white uppercase">🎟️ Visitor Pre-Ticketing</h3>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Add Pre-Ticket --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Schedule a Visitor</h4>
                <form method="POST" action="{{ route('portal.hr.pre-tickets.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Visitor Name *</label>
                            <input type="text" name="visitor_name" required maxlength="255"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Full name">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Company</label>
                            <input type="text" name="visitor_company" maxlength="255"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Company name">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Phone</label>
                            <input type="text" name="visitor_phone" maxlength="30"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="+233 XX XXX XXXX">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Host *</label>
                            <select name="host_id" required
                                    class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="">Select Host</option>
                                @foreach($staff as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Purpose *</label>
                            <input type="text" name="purpose" required maxlength="1000"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Purpose of visit (meeting, interview, delivery...)">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Expected Arrival *</label>
                            <input type="datetime-local" name="expected_arrival" required
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                        🎟️ Create Pre-Ticket
                    </button>
                </form>
            </div>
            {{-- Pre-Ticket Ledger --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Upcoming Visitors</h4>
                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                    @forelse($preTickets ?? [] as $pt)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-black/20 p-3 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-brand-white">{{ $pt->visitor_name }}
                                        @if($pt->visitor_company)
                                            <span class="text-brand-white/40 font-normal">— {{ $pt->visitor_company }}</span>
                                        @endif
                                    </p>
                                    <p class="text-brand-ash text-[10px] mt-0.5">{{ $pt->purpose }}</p>
                                    <p class="text-brand-ash text-[10px]">Host: {{ $pt->host?->name }} · {{ $pt->expected_arrival?->format('d M Y h:i A') }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    @if($pt->status === 'arrived')
                                        <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 text-[10px] text-emerald-400">✅ Arrived</span>
                                    @elseif($pt->status === 'pending')
                                        <form method="POST" action="{{ route('portal.hr.pre-tickets.arrive', $pt) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="rounded-lg bg-sky-600/20 border border-sky-500/30 px-2 py-1 text-[10px] text-sky-400 hover:bg-sky-600/40 transition-all">
                                                Mark Arrived
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/30 italic text-center py-8">No visitor pre-tickets scheduled.</p>
                    @endforelse
                </div>
                @if($preTickets instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $preTickets->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Phone & Vendor Directory ────────────────────────────────────────────── --}}
    <div class="mt-6 glass-panel rounded-2xl p-6 border border-brand-white/10 bg-brand-white/5">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-ash">Phase 3</p>
                <h3 class="text-lg font-display text-brand-white uppercase">📞 Corporate Phone & Vendor Directory</h3>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Add Entry Form --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Add Directory Entry</h4>
                <form method="POST" action="{{ route('portal.hr.directory.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Name *</label>
                            <input type="text" name="name" required
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="Full name">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Category *</label>
                            <select name="category" required class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white">
                                <option value="staff">Staff</option>
                                <option value="vendor">Vendor</option>
                                <option value="client">Client</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Job Title</label>
                            <input type="text" name="job_title"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Company</label>
                            <input type="text" name="company"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Phone</label>
                            <input type="text" name="phone" maxlength="30"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"
                                   placeholder="+233 XX XXX XXXX">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-brand-ash mb-1">Email</label>
                            <input type="email" name="email"
                                   class="w-full rounded-xl border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-brand-red hover:bg-brand-red-dark py-2.5 text-xs uppercase tracking-widest font-semibold text-white transition-all">
                        Add to Directory
                    </button>
                </form>
            </div>
            {{-- Directory Listing --}}
            <div class="rounded-xl border border-brand-white/10 bg-brand-white/5 p-5">
                <h4 class="text-xs uppercase tracking-widest text-brand-ash font-semibold mb-4">Directory</h4>
                <div class="space-y-2 max-h-[400px] overflow-y-auto pr-1">
                    @forelse($directoryEntries ?? [] as $entry)
                        <div class="rounded-xl border border-brand-white/10 bg-brand-black/20 px-4 py-3 flex items-center justify-between gap-3 text-xs">
                            <div>
                                <p class="font-semibold text-brand-white">{{ $entry->name }}</p>
                                <p class="text-brand-ash text-[10px]">{{ $entry->job_title }} · {{ $entry->company }}</p>
                                <p class="text-brand-ash text-[10px]">📞 {{ $entry->phone ?? '—' }} @if($entry->email) · ✉ {{ $entry->email }} @endif</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full border border-brand-white/10 px-2 py-0.5 text-[10px] text-brand-white/50 capitalize">{{ $entry->category }}</span>
                                <form method="POST" action="{{ route('portal.hr.directory.destroy', $entry) }}" onsubmit="return confirm('Remove entry?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-brand-red/60 hover:text-brand-red transition-colors">✕</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-brand-white/30 italic text-center py-8">Directory is empty. Add entries using the form.</p>
                    @endforelse
                </div>
                @if($directoryEntries instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4 pt-4 border-t border-brand-white/10">
                        {{ $directoryEntries->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function toggleHrAnnouncementAudience(value) {
            const departments = document.getElementById('announcement_departments');
            const staff = document.getElementById('announcement_staff');

            if (!departments || !staff) return;

            departments.classList.toggle('hidden', value !== 'departments');
            staff.classList.toggle('hidden', value !== 'selected');
        }
    </script>
</x-app-layout>
