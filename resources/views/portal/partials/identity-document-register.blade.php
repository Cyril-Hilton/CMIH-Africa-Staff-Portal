@php
    $completeIdentityCount = $identityDocuments->filter->hasCompleteIdentityDocument()->count();
    $notSubmittedIdentityCount = $identityDocuments
        ->reject->hasCompleteIdentityDocument()
        ->count();
@endphp

<section class="border-y border-brand-white/10 py-6">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.25em] text-brand-ash">Confidential Staff Records</p>
            <h2 class="mt-1 text-xl font-semibold text-brand-white">Identity Verification Register</h2>
            <p class="mt-1 text-xs text-brand-white/50">National ID and passport documents are stored privately and available only to HR Managers, CVO, and Super Admin.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-[10px] font-semibold uppercase tracking-wider">
            <span class="rounded-md border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-emerald-300">{{ $completeIdentityCount }} complete</span>
            <span class="rounded-md border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-sky-200">{{ $notSubmittedIdentityCount }} optional / not complete</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-xs">
            <thead class="border-b border-brand-white/10 text-[10px] uppercase tracking-wider text-brand-white/45">
                <tr>
                    <th class="px-3 py-3 font-semibold">Staff</th>
                    <th class="px-3 py-3 font-semibold">Nationality</th>
                    <th class="px-3 py-3 font-semibold">Document</th>
                    <th class="px-3 py-3 font-semibold">Status</th>
                    <th class="px-3 py-3 font-semibold">Secure Files</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-white/5">
                @foreach($identityDocuments as $staffIdentity)
                    @php
                        $documentType = $staffIdentity->effectiveIdentityDocumentType();
                        $nationality = \App\Models\User::nationalityOptions()[$staffIdentity->identityNationalityCode()] ?? 'Not selected';
                        $isComplete = $staffIdentity->hasCompleteIdentityDocument();
                    @endphp
                    <tr class="align-top text-brand-white/70">
                        <td class="px-3 py-3">
                            <p class="font-semibold text-brand-white">{{ $staffIdentity->name }}</p>
                            <p class="mt-1 text-[10px] text-brand-white/35">{{ $staffIdentity->email }}</p>
                        </td>
                        <td class="px-3 py-3">{{ $nationality }}</td>
                        <td class="px-3 py-3">
                            <p>{{ $documentType === 'passport' ? 'Passport' : \App\Models\User::nationalIdLabelFor($staffIdentity->identityNationalityCode()) }}</p>
                            <p class="mt-1 font-mono text-[10px] text-brand-white/45">
                                {{ $documentType === 'passport' ? ($staffIdentity->passport_number ?? 'No number') : ($staffIdentity->effectiveNationalIdNumber() ?? 'No number') }}
                            </p>
                        </td>
                        <td class="px-3 py-3">
                            <span class="{{ $isComplete ? 'text-emerald-300' : 'text-sky-200' }}">
                                {{ $isComplete ? 'Complete' : 'Optional / not complete' }}
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex min-w-40 flex-wrap gap-2">
                                @if($staffIdentity->nationalIdFrontDocumentPath())
                                    <a href="{{ route('portal.payroll.document', [$staffIdentity, 'national-id-front']) }}" target="_blank" rel="noopener" class="rounded-md border border-brand-white/10 px-2 py-1 text-amber-200 hover:bg-brand-white/5">ID Main/Front</a>
                                @endif
                                @if($staffIdentity->nationalIdBackDocumentPath())
                                    <a href="{{ route('portal.payroll.document', [$staffIdentity, 'national-id-back']) }}" target="_blank" rel="noopener" class="rounded-md border border-brand-white/10 px-2 py-1 text-amber-200 hover:bg-brand-white/5">ID Back</a>
                                @endif
                                @if($staffIdentity->passport_photo_path)
                                    <a href="{{ route('portal.payroll.document', [$staffIdentity, 'passport-document']) }}" target="_blank" rel="noopener" class="rounded-md border border-brand-white/10 px-2 py-1 text-amber-200 hover:bg-brand-white/5">Passport Page</a>
                                @endif
                                @if(! $staffIdentity->nationalIdFrontDocumentPath() && ! $staffIdentity->nationalIdBackDocumentPath() && ! $staffIdentity->passport_photo_path)
                                    <span class="text-brand-white/30">No files submitted</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
