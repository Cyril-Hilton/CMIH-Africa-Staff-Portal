<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\IdentityVerification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $departments = [
            'hr_admin', 'finance', 'client_relations',
            'operations_projects', 'brands_marketing', 'creatives',
            // legacy values still accepted
            'admin', 'transport', 'client_service', 'operations', 'brands',
        ];

        $jobLevels = [
            'Support Staff', 'Executive', 'Senior Executive',
            'Assistant Manager', 'Manager', 'Department Head', 'CVO',
        ];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'job_title'     => ['nullable', 'string', 'max:255'],
            'position_title' => ['nullable', 'string', Rule::in($jobLevels)],
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:' . now()->subYears(18)->toDateString(),
                'after_or_equal:'  . now()->subYears(65)->toDateString(),
            ],
            'start_date'    => ['nullable', 'date'],
            'department'    => ['nullable', 'string', Rule::in($departments)],
            'profile_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif,bmp,gif,tif,tiff', 'max:4096'],
            ...IdentityVerification::rules(),
            'mute_sounds'   => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            IdentityVerification::addCompletenessErrors($validator, $this, $this->user());
        });
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'You must be at least 18 years old.',
            'date_of_birth.after_or_equal'  => 'Age must be under 65 years old.',
            'position_title.in'             => 'Please select a valid job level from the list.',
            'contact_email.email'           => 'Please enter a valid personal email address.',
            'phone.max'                     => 'Please enter a valid phone number.',
        ];
    }
}
