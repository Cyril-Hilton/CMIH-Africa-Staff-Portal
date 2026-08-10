@extends('layouts.site')

@section('title', 'Brands Platform Admin')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Admin Console</p>
                    <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">Brand Platform Control</h1>
                    <p class="mt-2 text-sm text-brand-white/60">Create brand activations, assign staff, publish updates, issue temporary client links, and audit activity.</p>
                </div>
                <a href="{{ route('brands-platform.index') }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Brands Home</a>
            </div>

            @if(session('status'))
                <div class="mb-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif
            @if(session('client_link'))
                <div class="mb-5 rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4 text-sm text-brand-white/70">
                    Temporary client link:
                    <a href="{{ session('client_link') }}" class="font-semibold text-brand-white underline">{{ session('client_link') }}</a>
                </div>
            @endif

            <form method="POST" action="{{ route('brands-platform.admin.brands.store') }}" enctype="multipart/form-data" class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-red">Add Brand & Activation</p>
                        <h2 class="mt-1 text-xl font-semibold text-brand-white">Activation Execution Plan</h2>
                    </div>
                    <button class="rounded-md bg-brand-red px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white hover:text-brand-black">Save Plan</button>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input name="name" required placeholder="Brand name" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <select name="category" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            @foreach(['Personal Care', 'Beverage', 'Food', 'Home Care', 'Beauty', 'Telecommunications', 'Other'] as $category)
                                <option>{{ $category }}</option>
                            @endforeach
                        </select>
                        <input name="headline" placeholder="Brand headline" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30 sm:col-span-2">
                        <textarea name="description" rows="3" placeholder="Brand description" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30 sm:col-span-2"></textarea>
                        <input name="primary_color" placeholder="Primary colour e.g. #e50914" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="secondary_color" placeholder="Secondary colour" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <label class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-xs text-brand-white/55 sm:col-span-2">
                            Brand logo
                            <input name="logo" type="file" accept="image/*,.svg" class="mt-2 block w-full text-sm text-brand-white">
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <input name="activation_name" placeholder="Activation name" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30 sm:col-span-2">
                        <select name="activation_type" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                            @foreach(['sampling', 'sales', 'consumer_capture', 'retail_redemption', 'merchandising', 'activation'] as $type)
                                <option value="{{ $type }}">{{ \Illuminate\Support\Str::headline($type) }}</option>
                            @endforeach
                        </select>
                        <input name="target_unit" placeholder="Target unit e.g. Samples" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30">
                        <input name="starts_at" type="date" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                        <input name="ends_at" type="date" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                        <input name="target_reach" type="number" min="0" placeholder="Overall target" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30 sm:col-span-2">
                        <textarea name="activation_description" rows="3" placeholder="Activation description" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white placeholder-brand-white/30 sm:col-span-2"></textarea>
                        <label class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-xs text-brand-white/55 sm:col-span-2">
                            Activation banner
                            <input name="banner" type="file" accept="image/*" class="mt-2 block w-full text-sm text-brand-white">
                        </label>
                        <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-3 sm:col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-brand-white/40">Modules</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach([
                                    'publication' => 'Publication',
                                    'consumer_form' => 'Consumer Form',
                                    'agency_reporting' => 'Agency Reporting',
                                    'coupons_rewards' => 'Coupons / Rewards',
                                    'geofence' => 'Geofence',
                                    'retail_scanner' => 'Retail Scanner',
                                    'merchandising' => 'Merchandising',
                                ] as $value => $label)
                                    <label class="flex items-center gap-2 text-xs text-brand-white/65">
                                        <input type="checkbox" name="modules[]" value="{{ $value }}" class="rounded border-brand-white/20 bg-brand-black" @checked(in_array($value, ['publication', 'consumer_form', 'agency_reporting'], true))>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    @for($i = 0; $i < 3; $i++)
                        <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-brand-white/40">Location {{ $i + 1 }}</p>
                            <input name="locations[{{ $i }}][name]" placeholder="Location name" class="mt-3 w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <input name="locations[{{ $i }}][target]" type="number" min="0" placeholder="Target" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                                <input name="locations[{{ $i }}][daily_target]" type="number" min="0" placeholder="Daily" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                            </div>
                            <select name="locations[{{ $i }}][staff_ids][]" multiple class="mt-3 h-28 w-full rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white">
                                @foreach($staff as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }} - {{ $member->department ?: 'No dept' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
            </form>

            <div class="mt-8 grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Brands</p>
                    <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format($brands->count()) }}</p>
                </div>
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Activations</p>
                    <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format($brands->sum('activations_count')) }}</p>
                </div>
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Available Staff</p>
                    <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format(max(0, $availableStaff)) }}</p>
                </div>
                <div class="rounded-lg border border-brand-white/10 bg-brand-white/[0.04] p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Field Updates</p>
                    <p class="mt-2 text-3xl font-semibold text-brand-white">{{ number_format($brands->sum('field_activities_count')) }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-5">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Staff Productivity By Role</p>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    @forelse($roleProductivity as $row)
                        <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-4">
                            <p class="text-sm font-semibold text-brand-white">{{ \Illuminate\Support\Str::headline($row->staff_role) }}</p>
                            <p class="mt-2 text-xs text-brand-white/55">{{ number_format($row->updates) }} updates</p>
                            <p class="text-xs text-brand-white/55">{{ number_format($row->units) }} units - {{ number_format($row->conversions) }} conversions</p>
                        </div>
                    @empty
                        <p class="text-sm text-brand-white/40 md:col-span-4">No staff activity has been recorded yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="mt-8 grid gap-4 lg:grid-cols-2">
                @foreach($brands as $brand)
                    <article class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">{{ $brand->category ?: 'Brand' }}</p>
                                <h2 class="mt-1 text-2xl font-semibold text-brand-white">{{ $brand->name }}</h2>
                                <p class="mt-1 text-xs text-brand-white/45">{{ number_format($brand->activations_count) }} activations - {{ number_format($brand->consumer_entries_count) }} entries - {{ number_format($brand->field_activities_count) }} updates</p>
                            </div>
                            @if($brand->logoUrl())
                                <img src="{{ $brand->logoUrl('dark') ?: $brand->logoUrl() }}" alt="{{ $brand->name }}" class="h-12 max-w-24 object-contain">
                            @endif
                        </div>

                        <form method="POST" action="{{ route('brands-platform.admin.assignments.store', $brand->slug ?: $brand->id) }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_170px_auto]">
                            @csrf
                            <select name="user_id" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                                <option value="">Select staff</option>
                                @foreach($staff as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }} - {{ $member->department ?: 'No dept' }}</option>
                                @endforeach
                            </select>
                            <select name="role" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                                @foreach([
                                    'agency_staff' => 'Agency Staff',
                                    'supporting_staff' => 'Supporting Staff',
                                    'brand_admin' => 'Brand Admin',
                                    'promoter' => 'Promoter',
                                    'sales_personnel' => 'Sales Personnel',
                                    'retail_staff' => 'Retail Staff',
                                    'field_supervisor' => 'Field Supervisor',
                                    'merchandiser' => 'Merchandiser',
                                ] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-md bg-brand-red px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white hover:text-brand-black">Assign</button>
                        </form>

                        <form method="POST" action="{{ route('brands-platform.admin.publications.store', $brand->slug ?: $brand->id) }}" enctype="multipart/form-data" class="mt-4 grid gap-3">
                            @csrf
                            <p class="text-[10px] font-bold uppercase tracking-wider text-brand-white/40">Publish Brand Update</p>
                            <input name="title" placeholder="Publication title" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input name="category" placeholder="Category" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30">
                                <select name="status" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white">
                                    <option value="published">Published</option>
                                    <option value="draft">Draft</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <textarea name="summary" rows="2" placeholder="Short update" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"></textarea>
                            <textarea name="body" rows="4" placeholder="Full publication story, promo details, recap or activation note" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-2 text-sm text-brand-white placeholder-brand-white/30"></textarea>
                            <label class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-xs text-brand-white/55">
                                Publication image
                                <input name="image" type="file" accept="image/*" class="mt-2 block w-full text-sm text-brand-white">
                            </label>
                            <button class="self-start rounded-md border border-brand-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Publish</button>
                        </form>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @forelse($brand->staffAssignments->where('is_active', true)->take(10) as $assignment)
                                <span class="rounded-full border border-brand-white/10 bg-brand-black/50 px-3 py-1.5 text-[10px] text-brand-white/65">{{ $assignment->user?->name }} - {{ \Illuminate\Support\Str::headline($assignment->role) }}</span>
                            @empty
                                <span class="text-xs text-brand-white/35">No assigned staff yet.</span>
                            @endforelse
                        </div>

                        <div class="mt-4 space-y-2">
                            @foreach($brand->activations->take(3) as $activation)
                                <div class="rounded-md border border-brand-white/10 bg-brand-black/35 p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-brand-white">{{ $activation->name }}</p>
                                            <p class="text-[10px] uppercase tracking-wider text-brand-white/40">{{ \Illuminate\Support\Str::headline($activation->status) }} - target {{ number_format($activation->target_reach) }} {{ $activation->target_unit }}</p>
                                        </div>
                                        <form method="POST" action="{{ route('brands-platform.admin.client-link.generate', $activation) }}" class="flex gap-2">
                                            @csrf
                                            <select name="duration" class="rounded-md border border-brand-white/10 bg-brand-black/50 px-2 py-2 text-xs text-brand-white">
                                                <option value="1h">1 hour</option>
                                                <option value="6h">6 hours</option>
                                                <option value="24h">24 hours</option>
                                                <option value="7d">7 days</option>
                                            </select>
                                            <button class="rounded-md bg-brand-white px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-black hover:bg-brand-red hover:text-brand-white">Client Link</button>
                                        </form>
                                    </div>
                                    @if($activation->activation_plan)
                                        <p class="mt-2 text-xs text-brand-white/45">
                                            {{ count($activation->activation_plan['locations'] ?? []) }} locations,
                                            {{ count($activation->activation_plan['assigned_staff_ids'] ?? []) }} assigned staff,
                                            {{ number_format($activation->activation_plan['unallocated_target'] ?? 0) }} unallocated target.
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8 rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Staff Database</p>
                        <h2 class="mt-1 text-xl font-semibold text-brand-white">Current Brand Assignments</h2>
                    </div>
                    <a href="{{ route('brands-platform.admin.staff-feed') }}" class="rounded-md border border-brand-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Staff API</a>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                            <tr><th class="px-3 py-2">Brand</th><th class="px-3 py-2">Staff</th><th class="px-3 py-2">Department</th><th class="px-3 py-2">Role</th><th class="px-3 py-2">Assigned By</th><th class="px-3 py-2">Action</th></tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                            @forelse($assignments as $assignment)
                                <tr>
                                    <td class="px-3 py-3">{{ $assignment->brand?->name }}</td>
                                    <td class="px-3 py-3">{{ $assignment->user?->name }}</td>
                                    <td class="px-3 py-3">{{ $assignment->user?->department ?: 'N/A' }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($assignment->role) }}</td>
                                    <td class="px-3 py-3">{{ $assignment->assigner?->name ?: 'System' }}</td>
                                    <td class="px-3 py-3">
                                        <form method="POST" action="{{ route('brands-platform.admin.assignments.destroy', $assignment) }}" onsubmit="return confirm('Remove this brand assignment?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-md bg-brand-red/15 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-brand-red hover:bg-brand-red hover:text-brand-white">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-brand-white/40">No assignments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $assignments->links() }}</div>
            </div>

            <div class="mt-8 rounded-lg border border-brand-white/10 bg-brand-white/[0.035] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">Activity Logs</p>
                        <h2 class="mt-1 text-xl font-semibold text-brand-white">Platform Audit Trail</h2>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-brand-white/40">
                            <tr><th class="px-3 py-2">Time</th><th class="px-3 py-2">Account</th><th class="px-3 py-2">Action</th><th class="px-3 py-2">Context</th><th class="px-3 py-2">Brand</th><th class="px-3 py-2">Activation</th></tr>
                        </thead>
                        <tbody class="divide-y divide-brand-white/5 text-brand-white/75">
                            @forelse($activityLogs as $log)
                                <tr>
                                    <td class="px-3 py-3">{{ $log->created_at?->format('M d, H:i') }}</td>
                                    <td class="px-3 py-3">{{ $log->user?->name ?: 'Public' }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($log->action) }}</td>
                                    <td class="px-3 py-3">{{ \Illuminate\Support\Str::headline($log->context) }}</td>
                                    <td class="px-3 py-3">{{ $log->brand?->name ?: 'N/A' }}</td>
                                    <td class="px-3 py-3">{{ $log->activation?->name ?: 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-brand-white/40">No activity logs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $activityLogs->links() }}</div>
            </div>
        </div>
    </section>
@endsection
