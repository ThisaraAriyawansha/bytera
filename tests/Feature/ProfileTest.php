<?php

namespace Tests\Feature;

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

    public function test_email_changes_immediately_without_sending_mail(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->put(route('profile.email.update'), ['email' => 'New@Example.com', 'current_password' => 'password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Your email has been changed to new@example.com.');

        $this->assertSame('new@example.com', $user->fresh()->email);
        Mail::assertNothingSent();
    }

    public function test_email_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->put(route('profile.email.update'), ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('email', ['current_password' => 'Your current password is incorrect.']);

        $this->assertSame('old@example.com', $user->fresh()->email);
    }

    public function test_email_change_rejects_an_address_already_in_use(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.email.update'), ['email' => 'taken@example.com', 'current_password' => 'password'])
            ->assertSessionHasErrorsIn('email', ['email' => 'This email is already used by another account.']);

        $this->assertNotSame('taken@example.com', $user->fresh()->email);
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
