<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WorkEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = strtolower(substr(strrchr((string) $value, '@') ?: '', 1));
        if (! in_array($domain, config('cmih.work_email_domains', ['cmih.africa', 'cmihafrica.com']), true)) {
            $fail('Use a CMIH work email address (cmihafrica.com or cmih.africa).');
        }
    }
}
