<?php

namespace App\Support;

/**
 * Central password policy for create, reset, and profile change paths.
 */
class PasswordPolicy
{
    public const MIN_LENGTH = 10;

    public const DESCRIPTION = 'At least 10 characters with uppercase, lowercase, a number, and a special character.';

    /**
     * @return array{valid: bool, error?: string}
     */
    public static function validate(string $password): array
    {
        if (strlen($password) < self::MIN_LENGTH) {
            return ['valid' => false, 'error' => 'Password must be at least ' . self::MIN_LENGTH . ' characters.'];
        }

        if (! preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must include at least one uppercase letter.'];
        }

        if (! preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must include at least one lowercase letter.'];
        }

        if (! preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must include at least one number.'];
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must include at least one special character.'];
        }

        return ['valid' => true];
    }

    /**
     * Laravel validation rule string for FormRequest / $request->validate().
     */
    public static function laravelRule(): string
    {
        return 'required|string|min:' . self::MIN_LENGTH
            . '|regex:/[A-Z]/|regex:/[a-z]/|regex:/[0-9]/|regex:/[^A-Za-z0-9]/';
    }
}
