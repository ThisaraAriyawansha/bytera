<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active'])->get('/_active-test', fn () => 'ok');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_active_user_passes_through(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/_active-test')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_inactive_user_is_logged_out_and_redirected_to_login(): void
    {
        $this->actingAs(User::factory()->inactive()->create())
            ->get('/_active-test')
            ->assertRedirect('/auth/login')
            ->assertSessionHasErrors(['email' => 'Your account has been disabled']);

        $this->assertGuest();
    }

    public function test_access_restricted_component_renders_message(): void
    {
        $this->blade('<x-access-restricted message="You don\'t have permission to view the GRN page." />')
            ->assertSee('Access Restricted')
            ->assertSee('You don&#039;t have permission to view the GRN page.', false);
    }
}
