<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\MerchandiserAttendance;
use App\Models\MerchandiserPcmClockin;
use App\Models\MerchandiserPjpClockin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IdentityVerification
{
    public const NATIONAL_ID = 'national_id';
    public const PASSPORT = 'passport';

    public static function rules(): array
    {
        return [
            'nationality_code' => ['nullable', 'string', Rule::in(array_keys(config('identity.nationalities', [])))],
            'identity_document_type' => ['nullable', 'string', Rule::in([self::NATIONAL_ID, self::PASSPORT])],
            'national_id_number' => ['nullable', 'string', 'max:100'],
            'national_id_front' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'national_id_back' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'passport_number' => ['nullable', 'string', 'max:64'],
            'passport_document' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
        ];
    }

    public static function addCompletenessErrors(
        Validator $validator,
        Request $request,
        User $user
    ): void {
        if (! $user->requiresIdentityDocument()) {
            return;
        }

        $hasIdentityInput = $request->filled('nationality_code')
            || $request->filled('national_id_number')
            || $request->filled('passport_number')
            || $request->hasFile('national_id_front')
            || $request->hasFile('national_id_back')
            || $request->hasFile('passport_document');

        if (! $hasIdentityInput && ! self::hasClockedInToday($user)) {
            return;
        }

        $nationality = strtoupper(trim((string) $request->input(
            'nationality_code',
            $user->identityNationalityCode()
        )));
        $documentType = $request->input(
            'identity_document_type',
            $user->effectiveIdentityDocumentType()
        );

        if ($nationality === '') {
            $validator->errors()->add('nationality_code', 'Select your nationality.');
        }

        if (! in_array($documentType, [self::NATIONAL_ID, self::PASSPORT], true)) {
            $validator->errors()->add('identity_document_type', 'Choose a national ID or passport.');

            return;
        }

        if ($documentType === self::PASSPORT) {
            $passportNumber = $request->exists('passport_number')
                ? trim((string) $request->input('passport_number'))
                : trim((string) $user->passport_number);

            if ($passportNumber === '') {
                $validator->errors()->add('passport_number', 'Enter your passport number.');
            }

            if (! $request->hasFile('passport_document') && ! $user->passport_photo_path) {
                $validator->errors()->add(
                    'passport_document',
                    'Upload a clear scan or photo of the passport biodata/details page.'
                );
            }

            return;
        }

        $sameNationalId = $nationality !== ''
            && $nationality === $user->identityNationalityCode()
            && $user->effectiveIdentityDocumentType() === self::NATIONAL_ID;
        $nationalIdNumber = $request->exists('national_id_number')
            ? trim((string) $request->input('national_id_number'))
            : ($sameNationalId ? trim((string) $user->effectiveNationalIdNumber()) : '');

        if ($nationalIdNumber === '') {
            $validator->errors()->add(
                'national_id_number',
                'Enter the number shown on your '.User::nationalIdLabelFor($nationality).'.'
            );
        }

        if (! $request->hasFile('national_id_front')
            && (! $sameNationalId || ! $user->nationalIdFrontDocumentPath())) {
            $validator->errors()->add(
                'national_id_front',
                'Upload the main/front image of your '.User::nationalIdLabelFor($nationality).'.'
            );
        }

        if (User::nationalIdRequiresBack($nationality)
            && ! $request->hasFile('national_id_back')
            && (! $sameNationalId || ! $user->nationalIdBackDocumentPath())) {
            $validator->errors()->add(
                'national_id_back',
                'Upload the back image of your '.User::nationalIdLabelFor($nationality).'.'
            );
        }
    }

    public static function hasClockedInToday(User $user): bool
    {
        $today = Carbon::today();

        return Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_at', $today)
            ->exists()
            || MerchandiserAttendance::where('user_id', $user->id)
                ->whereDate('clock_in_time', $today)
                ->exists()
            || MerchandiserPcmClockin::where('user_id', $user->id)
                ->whereDate('clocked_in_at', $today)
                ->exists()
            || MerchandiserPjpClockin::where('user_id', $user->id)
                ->whereDate('clocked_in_at', $today)
                ->exists();
    }
}
