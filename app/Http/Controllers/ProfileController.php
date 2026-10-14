<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\ChangeEmailRequest;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
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
     * Change the email right away after checking the current password.
     */
    public function updateEmail(ChangeEmailRequest $request): RedirectResponse
    {
        $newEmail = $request->validated('email');

        $request->user()->update(['email' => $newEmail]);

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
}
