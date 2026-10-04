<?php

namespace App\Rules;

use App\Services\Turnstile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTurnstileToken implements ValidationRule
{
    public function __construct(private ?string $remoteIp = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(Turnstile::class)->verify($value, $this->remoteIp)) {
            $fail('Verification failed. Please complete the check and try again.');
        }
    }
}
