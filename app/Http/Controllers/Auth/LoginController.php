<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the sign in / forgot password / reset password page.
     */
    public function create(): View
    {
        return view('auth.login', [
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    /**
     * Start a session for valid, active credentials.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', Str::lower(trim($request->validated('email'))))
            ->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'Your account has been disabled']);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json([
            'redirect' => redirect()->intended(route('dashboard'))->getTargetUrl(),
        ]);
    }

    /**
     * End the current session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
