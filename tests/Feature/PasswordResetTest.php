<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetOtp;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const GENERIC_MESSAGE = 'If an account exists for that email, a 6-digit code has been sent. It is valid for 10 minutes.';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);
        Mail::fake();
    }

    public function test_code_is_stored_and_emailed(): void
    {
        $user = User::factory()->create();

        $this->requestCode($user->email)->assertOk()->assertJson(['message' => self::GENERIC_MESSAGE]);

        $otp = PasswordResetOtp::query()->findOrFail($user->email);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp->otp);
        $this->assertSame($user->id, $otp->user_id);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, $otp->expires_at->timestamp, 5);

        Mail::assertSent(PasswordResetCodeMail::class, fn (PasswordResetCodeMail $mail) => $mail->hasTo($user->email) && $mail->code === $otp->otp);
    }

    public function test_unknown_email_gets_the_same_response_and_no_mail(): void
    {
        $this->requestCode('nobody@example.com')->assertOk()->assertJson(['message' => self::GENERIC_MESSAGE]);

        $this->assertDatabaseCount('password_reset_otps', 0);
        Mail::assertNothingSent();
    }

    public function test_resend_within_cooldown_does_not_issue_a_new_code(): void
    {
        $user = User::factory()->create();

        $this->requestCode($user->email);
        $firstCode = PasswordResetOtp::query()->findOrFail($user->email)->otp;

        $this->travel(30)->seconds();
        $this->requestCode($user->email)->assertOk()->assertJson(['message' => self::GENERIC_MESSAGE]);
        $this->assertSame($firstCode, PasswordResetOtp::query()->findOrFail($user->email)->otp);
        Mail::assertSentCount(1);

        $this->travel(31)->seconds();
        $this->requestCode($user->email);
        Mail::assertSentCount(2);
    }

    public function test_password_can_be_reset_with_the_code(): void
    {
        $user = User::factory()->create();
        $this->requestCode($user->email);
        $code = PasswordResetOtp::query()->findOrFail($user->email)->otp;

        $this->resetWith($user->email, $code, 'new-secret')
            ->assertOk()
            ->assertJson(['message' => 'Your password has been reset. You can now sign in.']);

        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
        $this->assertTrue(PasswordResetOtp::query()->findOrFail($user->email)->used);

        $this->resetWith($user->email, $code, 'another-secret')
            ->assertJsonValidationErrors(['code' => 'Invalid code. Please request a new one.']);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->requestCode($user->email);
        $code = PasswordResetOtp::query()->findOrFail($user->email)->otp;

        $this->travel(11)->minutes();

        $this->resetWith($user->email, $code, 'new-secret')
            ->assertJsonValidationErrors(['code' => 'This code has expired. Request a new one.']);
    }

    public function test_code_is_burned_after_five_wrong_attempts(): void
    {
        $user = User::factory()->create();
        $this->requestCode($user->email);
        $code = PasswordResetOtp::query()->findOrFail($user->email)->otp;
        $wrongCode = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 4) as $attempt) {
            $this->resetWith($user->email, $wrongCode, 'new-secret')
                ->assertJsonValidationErrors(['code' => 'Incorrect code. Please try again.']);
        }

        $this->resetWith($user->email, $wrongCode, 'new-secret')
            ->assertJsonValidationErrors(['code' => 'Too many attempts. Please request a new code.']);

        $this->resetWith($user->email, $code, 'new-secret')->assertJsonValidationErrors('code');
        $this->assertFalse(Hash::check('new-secret', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed_and_at_least_six_characters(): void
    {
        $this->postJson('/auth/reset-password', [
            'email' => 'someone@example.com',
            'code' => '123456',
            'password' => '12345',
            'password_confirmation' => '54321',
            'turnstile_token' => 'token',
        ])->assertJsonValidationErrors(['password']);
    }

    public function test_reset_requires_turnstile(): void
    {
        $this->postJson('/auth/reset-password', [
            'email' => 'someone@example.com',
            'code' => '123456',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ])->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_reset_code_email_has_html_and_text_versions_with_shop_name(): void
    {
        ShopSetting::query()->firstOrFail()->update(['name' => 'Test Shop', 'phone' => '0771234567']);

        $mail = new PasswordResetCodeMail('482913', ShopSetting::current());

        $mail->assertHasSubject('Your password reset code - Test Shop');
        $mail->assertSeeInHtml('482913');
        $mail->assertSeeInHtml('Test Shop');
        $mail->assertSeeInHtml('0771234567');
        $mail->assertSeeInText('482913');
        $mail->assertSeeInText('valid for 10 minutes');
        $mail->assertSeeInText('Test Shop');
    }

    private function requestCode(string $email): TestResponse
    {
        return $this->postJson('/auth/forgot-password', ['email' => $email, 'turnstile_token' => 'token']);
    }

    private function resetWith(string $email, string $code, string $password): TestResponse
    {
        return $this->postJson('/auth/reset-password', [
            'email' => $email,
            'code' => $code,
            'password' => $password,
            'password_confirmation' => $password,
            'turnstile_token' => 'token',
        ]);
    }
}
