<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class BrandsActivityValidation
{
    public function validate(array $data): void
    {
        foreach ($data as $field => $value) {
            if (str_ends_with($field, '_percent') && $value !== null && (! is_numeric($value) || $value < 0 || $value > 100)) {
                throw ValidationException::withMessages(['data.'.$field => 'Use a percentage between 0 and 100.']);
            }
            if (in_array($field, ['outlets_planned','outlets_visited','retailers_supported','merchandisers_deployed','promoters_deployed','training_completed','assets_planned','assets_delivered'], true) && $value !== null && (floor((float)$value) != (float)$value || $value < 0)) {
                throw ValidationException::withMessages(['data.'.$field => 'Use a whole number of items or people.']);
            }
        }
        // A plan is a target, not a hard ceiling: execution above plan is valid.
    }
}
