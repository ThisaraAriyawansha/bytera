<?php

namespace Tests\Feature;

use App\Mail\EmailChangeConfirmationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_user_can_update_name_and_phone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'Kasun Perera', 'phone' => '0771234567'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Profile saved.');

        $this->assertSame('Kasun Perera', $user->fresh()->name);
        $this->assertSame('0771234567', $user->fresh()->phone);
    }

    public function test_email_changes_only_after_the_emailed_link_is_clicked(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->post(route('profile.email.request'), ['email' => 'New@Example.com', 'current_password' => 'password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'We sent a verification link to new@example.com. Your email will change after you click it.');

        $this->assertSame('old@example.com', $user->fresh()->email);

        $confirmationUrl = null;
        Mail::assertSent(EmailChangeConfirmationMail::class, function (EmailChangeConfirmationMail $mail) use (&$confirmationUrl): bool {
            $confirmationUrl = $mail->confirmationUrl;

            return $mail->hasTo('new@example.com');
        });

        $this->actingAs($user)
            ->get($confirmationUrl)
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Your email has been changed to new@example.com.');

        $this->assertSame('new@example.com', $user->fresh()->email);

        $this->actingAs($user->fresh())
            ->get($confirmationUrl)
            ->assertSessionHasErrorsIn('email', ['email' => 'This verification link is no longer valid. Request a new one.']);
    }

    public function test_email_change_requires_the_current_password(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('profile.email.request'), ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('email', ['current_password' => 'Your current password is incorrect.']);

        Mail::assertNothingSent();
    }

    public function test_email_change_rejects_an_address_already_in_use(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs(User::factory()->create())
            ->post(route('profile.email.request'), ['email' => 'taken@example.com', 'current_password' => 'password'])
            ->assertSessionHasErrorsIn('email', ['email' => 'This email is already used by another account.']);

        Mail::assertNothingSent();
    }

    public function test_confirmation_fails_if_the_address_was_taken_meanwhile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.email.request'), ['email' => 'new@example.com', 'current_password' => 'password']);
        $confirmationUrl = Mail::sent(EmailChangeConfirmationMail::class)->first()->confirmationUrl;

        User::factory()->create(['email' => 'new@example.com']);

        $this->actingAs($user)
            ->get($confirmationUrl)
            ->assertSessionHasErrorsIn('email', ['email' => 'This email is already used by another account.']);
        $this->assertNotSame('new@example.com', $user->fresh()->email);
    }

    public function test_confirmation_link_cannot_be_tampered_with_or_used_by_someone_else(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.email.request'), ['email' => 'new@example.com', 'current_password' => 'password']);
        $confirmationUrl = Mail::sent(EmailChangeConfirmationMail::class)->first()->confirmationUrl;

        $this->actingAs($user)->get(str_replace('new%40example.com', 'evil%40example.com', $confirmationUrl))->assertForbidden();
        $this->actingAs(User::factory()->create())->get($confirmationUrl)->assertForbidden();

        $this->travel(61)->minutes();
        $this->actingAs($user)->get($confirmationUrl)->assertForbidden();

        $this->assertNotSame('new@example.com', $user->fresh()->email);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password.update'), ['current_password' => 'wrong', 'password' => 'secret1', 'password_confirmation' => 'secret1'])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)
            ->put(route('profile.password.update'), ['current_password' => 'password', 'password' => '12345', 'password_confirmation' => '12345'])
            ->assertSessionHasErrorsIn('password', 'password');

        $this->actingAs($user)
            ->put(route('profile.password.update'), ['current_password' => 'password', 'password' => 'secret1', 'password_confirmation' => 'secret1'])
            ->assertSessionHas('status', 'Password changed.');

        $this->assertTrue(Hash::check('secret1', $user->fresh()->password));
    }
}
