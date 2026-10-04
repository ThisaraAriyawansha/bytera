<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Turnstile
{
    /**
     * Cloudflare's server-side token verification endpoint.
     */
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Verify a Turnstile widget token with Cloudflare. Tokens are single-use.
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));
        } catch (ConnectionException $exception) {
            report($exception);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
