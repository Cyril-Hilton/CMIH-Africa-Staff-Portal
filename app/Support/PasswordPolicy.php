<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public const MIN_LENGTH = 9;

    public static function rules(): array
    {
        return [
            'string',
            Password::min(self::MIN_LENGTH)
                ->letters()
                ->numbers()
                ->symbols(),
        ];
    }

    public static function confirmedRules(): array
    {
        return [
            'required',
            'confirmed',
            ...self::rules(),
        ];
    }

    public static function description(): string
    {
        return 'Use more than 8 characters with at least one letter, one number, and one symbol.';
    }

    public static function generateTemporaryPassword(int $length = 14): string
    {
        $length = max($length, self::MIN_LENGTH);

        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $numbers = '23456789';
        $symbols = '!@#$%^&*?-_';
        $pool = $letters.$numbers.$symbols;

        $chars = [
            self::randomCharacter($letters),
            self::randomCharacter($numbers),
            self::randomCharacter($symbols),
        ];

        while (count($chars) < $length) {
            $chars[] = self::randomCharacter($pool);
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function randomCharacter(string $characters): string
    {
        return $characters[random_int(0, strlen($characters) - 1)];
    }
}
