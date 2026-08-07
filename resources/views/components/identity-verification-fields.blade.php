@props(['user'])

@php
    $identityComplete = $user->hasCompleteIdentityDocument();
    $identityStatus = $identityComplete ? 'Complete' : 'Optional';
    $identityBorder = $identityComplete
        ? 'border-emerald-500/20 bg-emerald-500/10'
        : 'border-sky-500/20 bg-sky-500/10';
    $identityBadge = $identityComplete
        ? 'border-emerald-500/30 text-emerald-300'
        : 'border-sky-500/30 text-sky-200';
    $selectedNationality = old('nationality_code', $user->identityNationalityCode());
    $selectedDocumentType = old('identity_document_type', $user->effectiveIdentityDocumentType());
    $nationalIdLabels = collect(\App\Models\User::nationalityOptions())
        ->mapWithKeys(fn ($country, $code) => [$code => \App\Models\User::nationalIdLabelFor($code)])
        ->all();
    $defaultNationalIdLabel = config('identity.default_national_id_label');
    $backRequiredNationalities = config('identity.national_id_back_required', []);
@endphp

<div
    class="rounded-xl border {{ $identityBorder }} p-4"
    x-data="{
        nationality: @js($selectedNationality),
        documentType: @js($selectedDocumentType),
        idLabels: @js($nationalIdLabels),
        defaultIdLabel: @js($defaultNationalIdLabel),
        backRequired: @js($backRequiredNationalities),
        idLabel() {
            return this.idLabels[this.nationality] || this.defaultIdLabel;
        }
    }"
>
    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.25em] text-brand-ash">Identity Verification</p>
            <h3 class="mt-1 text-lg font-semibold text-brand-white">Nationality and Identity Document</h3>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-brand-white/60">
                Select your nationality, then provide the appropriate national ID. Choose passport if you do not have a national ID card.
            </p>
            <p class="mt-2 text-xs text-sky-200/80">Identity verification is optional and does not restrict access to any portal tool.</p>
        </div>
        <span class="inline-flex w-fit rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wider {{ $identityBadge }}">
            {{ $identityStatus }}
        </span>
    </div>

    <div class="space-y-5">
        <div class="max-w-xl">
            <x-input-label for="nationality_code" :value="__('Nationality')" />
            <select
                id="nationality_code"
                name="nationality_code"
                x-model="nationality"
                class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/60 px-3 py-2 text-sm text-brand-white focus:border-brand-red focus:ring-brand-red"
            >
                <option value="">Select nationality</option>
                @foreach(\App\Models\User::nationalityOptions() as $code => $country)
                    <option value="{{ $code }}" @selected($selectedNationality === $code)>{{ $country }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('nationality_code')" class="mt-2" />
        </div>

        <fieldset>
            <legend class="text-xs font-semibold uppercase tracking-wider text-brand-white/70">Verification document</legend>
            <div class="mt-2 grid max-w-2xl grid-cols-1 gap-2 sm:grid-cols-2">
                <label
                    class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-3 text-sm transition"
                    :class="documentType === 'national_id' ? 'border-brand-red bg-brand-red/10 text-brand-white' : 'border-brand-white/10 bg-brand-black/20 text-brand-white/60'"
                >
                    <input type="radio" name="identity_document_type" value="national_id" x-model="documentType" class="text-brand-red focus:ring-brand-red">
                    <span>
                        <span class="block font-semibold">National ID</span>
                        <span class="block text-[10px] text-brand-white/45" x-text="idLabel()"></span>
                    </span>
                </label>
                <label
                    class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-3 text-sm transition"
                    :class="documentType === 'passport' ? 'border-brand-red bg-brand-red/10 text-brand-white' : 'border-brand-white/10 bg-brand-black/20 text-brand-white/60'"
                >
                    <input type="radio" name="identity_document_type" value="passport" x-model="documentType" class="text-brand-red focus:ring-brand-red">
                    <span>
                        <span class="block font-semibold">Passport</span>
                        <span class="block text-[10px] text-brand-white/45">Use when a national ID is unavailable</span>
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('identity_document_type')" class="mt-2" />
        </fieldset>

        <div x-show="documentType === 'national_id'" x-cloak class="grid gap-4 lg:grid-cols-3">
            <div>
                <label for="national_id_number" class="block text-sm font-medium text-brand-white">
                    <span x-text="idLabel()"></span> Number
                </label>
                <x-text-input
                    id="national_id_number"
                    name="national_id_number"
                    type="text"
                    :value="old('national_id_number', $user->effectiveNationalIdNumber())"
                    placeholder="Enter the ID number"
                    class="mt-1 w-full"
                />
                <x-input-error :messages="$errors->get('national_id_number')" class="mt-2" />
            </div>
            <div>
                <label for="national_id_front" class="block text-sm font-medium text-brand-white">
                    Main / Front Image
                </label>
                <input id="national_id_front" name="national_id_front" type="file" accept="image/*" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white file:mr-3 file:rounded-md file:border-0 file:bg-brand-white/10 file:px-3 file:py-2 file:text-xs file:text-brand-white" />
                <p class="mt-1 text-[10px] text-brand-white/40">{{ $user->nationalIdFrontDocumentPath() ? 'Main/front image is on file.' : 'No main/front image uploaded yet.' }}</p>
                <x-input-error :messages="$errors->get('national_id_front')" class="mt-2" />
            </div>
            <div>
                <label for="national_id_back" class="block text-sm font-medium text-brand-white">
                    Back / Reverse Image
                    <span class="text-[10px] font-normal text-brand-white/40" x-text="backRequired.includes(nationality) ? '(recommended for this ID)' : '(if applicable)'"></span>
                </label>
                <input id="national_id_back" name="national_id_back" type="file" accept="image/*" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white file:mr-3 file:rounded-md file:border-0 file:bg-brand-white/10 file:px-3 file:py-2 file:text-xs file:text-brand-white" />
                <p class="mt-1 text-[10px] text-brand-white/40">{{ $user->nationalIdBackDocumentPath() ? 'Back/reverse image is on file.' : 'No back/reverse image uploaded yet.' }}</p>
                <x-input-error :messages="$errors->get('national_id_back')" class="mt-2" />
            </div>
        </div>

        <div x-show="documentType === 'passport'" x-cloak class="grid gap-4 lg:grid-cols-2">
            <div>
                <x-input-label for="passport_number" :value="__('Passport Number')" />
                <x-text-input id="passport_number" name="passport_number" type="text" :value="old('passport_number', $user->passport_number)" placeholder="Enter passport number" class="mt-1 w-full" />
                <x-input-error :messages="$errors->get('passport_number')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="passport_document" :value="__('Passport Biodata / Details Page Image')" />
                <input id="passport_document" name="passport_document" type="file" accept="image/*" class="mt-1 w-full rounded-md border border-brand-white/10 bg-brand-black/40 px-3 py-2 text-sm text-brand-white file:mr-3 file:rounded-md file:border-0 file:bg-brand-white/10 file:px-3 file:py-2 file:text-xs file:text-brand-white" />
                <p class="mt-1 text-[10px] leading-relaxed text-brand-white/50">
                    Upload a clear scan or captured image of the actual passport biodata/details page issued by your country's passport authority. Do not upload a portrait, selfie, or ordinary face photo.
                </p>
                <p class="mt-1 text-[10px] text-brand-white/40">{{ $user->passport_photo_path ? 'Passport document image is on file.' : 'No passport document image uploaded yet.' }}</p>
                <x-input-error :messages="$errors->get('passport_document')" class="mt-2" />
            </div>
        </div>
    </div>
</div>
