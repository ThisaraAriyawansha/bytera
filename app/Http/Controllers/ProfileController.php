<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\ChangeEmailRequest;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Mail\EmailChangeConfirmationMail;
use App\Models\ShopSetting;
use App\Models\User;
use App\Rules\StrictEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    /**
     * How long an email change link stays valid.
     */
    public const EMAIL_LINK_TTL_MINUTES = 60;

    /**
     * Show the profile page.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Save the display name and phone.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return to_route('profile.edit')->with('status', 'Profile saved.');
    }

    /**
     * Email a confirmation link to the new address. The email only changes once it is clicked.
     */
    public function requestEmailChange(ChangeEmailRequest $request): RedirectResponse
    {
        $user = $request->user();
        $newEmail = $request->validated('email');

        $confirmationUrl = URL::temporarySignedRoute(
            'profile.email.confirm',
            now()->addMinutes(self::EMAIL_LINK_TTL_MINUTES),
            ['user' => $user->id, 'email' => $newEmail, 'hash' => self::emailHash($user->email)],
        );

        try {
            Mail::to($newEmail)->send(new EmailChangeConfirmationMail($user->name, $newEmail, $confirmationUrl, ShopSetting::current()));
        } catch (Throwable $exception) {
            report($exception);

            return to_route('profile.edit')
                ->withErrors(['email' => "We couldn't send the verification email. Please try again later."], 'email')
                ->withInput($request->only('email'));
        }

        return to_route('profile.edit')->with(
            'status',
            "We sent a verification link to {$newEmail}. Your email will change after you click it.",
        );
    }

    /**
     * Apply an email change from a signed confirmation link.
     */
    public function confirmEmailChange(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->is($user), 403);

        $newEmail = Str::lower((string) $request->query('email'));

        $error = DB::transaction(function () use ($user, $newEmail, $request): ?string {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! hash_equals(self::emailHash($user->email), (string) $request->query('hash'))) {
                return 'This verification link is no longer valid. Request a new one.';
            }

            if (! StrictEmail::isValid($newEmail)) {
                return 'This verification link is not valid.';
            }

            if (User::query()->where('email', $newEmail)->whereKeyNot($user->id)->exists()) {
                return 'This email is already used by another account.';
            }

            $user->update(['email' => $newEmail]);

            return null;
        });

        if ($error !== null) {
            return to_route('profile.edit')->withErrors(['email' => $error], 'email');
        }

        return to_route('profile.edit')->with('status', "Your email has been changed to {$newEmail}.");
    }

    /**
     * Change the password after checking the current one.
     */
    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        return to_route('profile.edit')->with('status', 'Password changed.');
    }

    /**
     * Tie a link to the email it was issued for, so it stops working once the email changes.
     */
    private static function emailHash(string $email): string
    {
        return hash_hmac('sha256', Str::lower($email), (string) config('app.key'));
    }
}
