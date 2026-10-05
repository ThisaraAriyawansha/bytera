<?php

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetOtp;
use App\Models\ShopSetting;
use App\Models\User;
use App\Rules\StrictEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetService
{
    public const CODE_TTL_MINUTES = 10;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    /**
     * Issue and email a fresh 6-digit code. Silently does nothing for unknown or
     * disabled accounts, or while the resend cooldown is running, so callers can
     * always answer with the same generic message.
     */
    public function sendCode(string $email): void
    {
        $email = Str::lower(trim($email));

        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->status !== 'active' || ! StrictEmail::isValid($user->email)) {
            return;
        }

        $code = DB::transaction(function () use ($user, $email): ?string {
            $existing = PasswordResetOtp::query()->whereKey($email)->lockForUpdate()->first();

            if ($existing !== null && $existing->created_at->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
                return null;
            }

            $existing?->delete();

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetOtp::query()->create([
                'email' => $email,
                'user_id' => $user->id,
                'otp' => $code,
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'used' => false,
                'attempts' => 0,
            ]);

            return $code;
        });

        if ($code === null) {
            return;
        }

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail($code, ShopSetting::current()));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Check the code and set the new password.
     *
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $code, string $password): void
    {
        $email = Str::lower(trim($email));

        $error = DB::transaction(function () use ($email, $code, $password): ?string {
            $otp = PasswordResetOtp::query()->whereKey($email)->lockForUpdate()->first();

            if ($otp === null || $otp->used) {
                return 'Invalid code. Please request a new one.';
            }

            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                return 'Too many attempts. Please request a new code.';
            }

            if ($otp->expires_at->isPast()) {
                return 'This code has expired. Request a new one.';
            }

            if (! hash_equals($otp->otp, $code)) {
                $otp->attempts++;
                $otp->used = $otp->attempts >= self::MAX_ATTEMPTS;
                $otp->save();

                return $otp->used
                    ? 'Too many attempts. Please request a new code.'
                    : 'Incorrect code. Please try again.';
            }

            $otp->update(['used' => true]);

            $otp->user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            return null;
        });

        if ($error !== null) {
            throw ValidationException::withMessages(['code' => $error]);
        }
    }
}
