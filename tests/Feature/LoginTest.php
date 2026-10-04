<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private bool $turnstilePasses = true;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([Turnstile::VERIFY_URL => fn () => Http::response(['success' => $this->turnstilePasses])]);
    }

    public function test_root_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/auth/login');
    }

    public function test_root_redirects_signed_in_users_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/dashboard');
    }

    public function test_login_page_shows_shop_name(): void
    {
        $this->get('/auth/login')
            ->assertOk()
            ->assertSee('<title>M-Fixpro POS</title>', false)
            ->assertSee('Welcome to M-Fixpro')
            ->assertSee('Manage repairs, sales and billing for your shop from one simple dashboard.');
    }

    public function test_seeded_super_admin_can_sign_in(): void
    {
        $this->postJson('/auth/login', [
            'email' => config('services.super_admin.email'),
            'password' => config('services.super_admin.password'),
            'turnstile_token' => 'token',
        ])->assertOk()->assertJson(['redirect' => route('dashboard')]);

        $this->assertAuthenticatedAs(User::query()->where('role', 'Super Admin')->first());
        $this->get('/dashboard')->assertOk()->assertSee('Dashboard');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'turnstile_token' => 'token',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Invalid email or password.']);

        $this->assertGuest();
    }

    public function test_inactive_user_is_refused(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'turnstile_token' => 'token',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Your account has been disabled']);

        $this->assertGuest();
    }

    public function test_failed_turnstile_verification_is_rejected(): void
    {
        $this->turnstilePasses = false;

        $user = User::factory()->create();

        $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'turnstile_token' => 'bad-token',
        ])->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');

        $this->assertGuest();
        Http::assertSent(fn ($request) => $request['response'] === 'bad-token');
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/auth/logout')
            ->assertRedirect('/auth/login');

        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/auth/login');
    }
}
