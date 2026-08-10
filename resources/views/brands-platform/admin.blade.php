@extends('layouts.site')

@section('title', 'Brands Platform Admin')

@section('content')
    <section class="bg-brand-black">
        <div class="mx-auto w-full max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.35em] text-brand-red">Admin Console</p>
                    <h1 class="mt-2 font-display text-5xl leading-none text-brand-white">Brand Access Control</h1>
                    <p class="mt-2 text-sm text-brand-white/60">Assign CMIH staff to the brands they manage or support. Super admin keeps access to every brand.</p>
                </div>
                <a href="{{ route('brands-platform.index') }}" class="rounded-md border border-brand-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-brand-white/60 hover:text-brand-white">Brands Home</a>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($brands as $brand)
                    <article class="rounded-lg border border-brand-white/10 bg-brand-white/[0.045] p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-brand-ash">{{ $brand->category ?: 'Brand' }}</p>
                                <h2 class="mt-1 text-2xl font-semibold text-brand-white">{{ $brand->name }}</h2>
                            </div>
                            @if($brand->logoUrl())
                                <img src="{{ $brand->logoUrl('dark') ?: $brand->logoUrl() }}" alt="{{ $brand->name }}" class="h-12 max-w-24 object-contain">
                            @endif
                        </div>

                        <form method="POST" action="{{ route('brands-platform.admin.assignments.store', $brand->slug ?: $brand->id) }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_160px_auto]">
                            @csrf
                            <select name="user_id" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                                <option value="">Select staff</option>
                                @foreach($staff as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }} - {{ $member->department ?: 'No dept' }}</option>
                                @endforeach
                            </select>
                            <select name="role" required class="rounded-md border border-brand-white/10 bg-brand-black/50 px-3 py-3 text-sm text-brand-white">
                                <option value="agency_staff">Agency Staff</option>
                                <option value="supporting_staff">Supporting Staff</option>
                                <option value="brand_admin">Brand Admin</option>
                            </select>
                            <button class="rounded-md bg-brand-red px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-brand-white hover:bg-brand-white hover:text-brand-black">Assign</button>
                        </form>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @forelse($brand->staffAssignments->where('is_active', true)->take(8) as $assignment)
                                <span class="rounded-full border border-brand-white/10 bg-brand-black/50 px-3 py-1.5 text-[10px] text-brand-white/65">{{ $assignment->user?->name }} - {{ \Illuminate\Support\Str::headline($assignment->role) }}</span>
                            @empty
                                <span class="text-xs text-brand-white/35">No assigned staff yet.</span>
                            @endforelse
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
        </div>
    </section>
@endsection
