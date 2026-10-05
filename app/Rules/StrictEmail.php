<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class StrictEmail implements ValidationRule
{
    /**
     * A single plain address: no whitespace, commas, semicolons or display names (SPEC §9).
     */
    public const PATTERN = '/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/D';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('Enter a valid email address.');
        }
    }

    /**
     * Whether the value is one plain address that is safe to send mail to. Every email endpoint checks
     * the stored recipient with this right before sending.
     */
    public static function isValid(mixed $value): bool
    {
        return is_string($value) && mb_strlen($value) <= 254 && preg_match(self::PATTERN, $value) === 1;
    }
}
