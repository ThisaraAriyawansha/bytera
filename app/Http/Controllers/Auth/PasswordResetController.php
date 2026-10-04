<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetCodeRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $passwordResets) {}

    /**
     * Email a reset code. The response is identical whether or not the account exists.
     */
    public function sendCode(SendPasswordResetCodeRequest $request): JsonResponse
    {
        $this->passwordResets->sendCode($request->validated('email'));

        return response()->json([
            'message' => 'If an account exists for that email, a 6-digit code has been sent. It is valid for 10 minutes.',
        ]);
    }

    /**
     * Set a new password using an emailed code.
     *
     * @throws ValidationException
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResets->resetPassword(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Your password has been reset. You can now sign in.',
        ]);
    }
}
