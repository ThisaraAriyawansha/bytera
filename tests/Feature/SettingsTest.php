<?php

namespace Tests\Feature;

use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_everyone_can_open_settings_but_only_admins_see_edit_controls(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Cashier']))
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Only an Admin can change these settings.')
            ->assertSee('Database Usage')
            ->assertDontSee('Add User')
            ->assertDontSee('Data Tools');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Add User')
            ->assertDontSee('Only an Admin can change these settings.')
            ->assertDontSee('Data Tools');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Data Tools');
    }

    public function test_super_admins_are_hidden_from_non_super_admins(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['email' => 'boss@example.com']);

        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $viewer) {
            $this->actingAs($viewer)->get(route('settings.index'))->assertDontSee('boss@example.com');
        }

        $this->actingAs($superAdmin)->get(route('settings.index'))->assertSee('boss@example.com');
    }

    public function test_admin_can_update_shop_info(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('settings.shop.update'), [
                'name' => 'Bytera',
                'phone' => '0771234567',
                'email' => 'Shop@Example.com',
                'address' => 'Colombo',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('status', 'Shop info saved.');

        $settings = ShopSetting::current();
        $this->assertSame('Bytera', $settings->name);
        $this->assertSame('shop@example.com', $settings->email);
        $this->assertSame(1, ShopSetting::query()->count());
    }

    public function test_shop_name_and_phone_are_required(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('settings.shop.update'), ['name' => '', 'phone' => ''])
            ->assertSessionHasErrorsIn('shop', ['name', 'phone']);
    }

    public function test_non_admin_cannot_change_shop_info_or_alert_emails(): void
    {
        $manager = User::factory()->create(['role' => 'Manager']);

        $this->actingAs($manager)->put(route('settings.shop.update'), ['name' => 'Hacked', 'phone' => '1'])->assertForbidden();
        $this->actingAs($manager)->post(route('settings.notify-emails.store'), ['email' => 'a@example.com'])->assertForbidden();
        $this->actingAs($manager)->delete(route('settings.notify-emails.destroy'), ['email' => 'a@example.com'])->assertForbidden();

        $this->assertSame('M-Fixpro', ShopSetting::current()->name);
        $this->assertSame([], ShopSetting::current()->notify_emails);
    }

    public function test_admin_can_add_and_remove_low_stock_recipients(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('settings.notify-emails.store'), ['email' => ' Stock@Example.com '])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('settings.notify-emails.store'), ['email' => 'owner@example.com']);
        $this->assertSame(['stock@example.com', 'owner@example.com'], ShopSetting::current()->notify_emails);

        $this->actingAs($admin)
            ->post(route('settings.notify-emails.store'), ['email' => 'stock@example.com'])
            ->assertSessionHasErrorsIn('notifyEmails', ['email' => 'This email is already on the list.']);

        $this->actingAs($admin)->delete(route('settings.notify-emails.destroy'), ['email' => 'stock@example.com']);
        $this->assertSame(['owner@example.com'], ShopSetting::current()->notify_emails);
    }

    public function test_recipient_must_be_a_single_plain_address(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['a@example.com,b@example.com', 'a@example.com; b@example.com', 'Name <a@example.com>', 'not-an-email'] as $email) {
            $this->actingAs($admin)
                ->post(route('settings.notify-emails.store'), ['email' => $email])
                ->assertSessionHasErrorsIn('notifyEmails', 'email');
        }

        $this->assertSame([], ShopSetting::current()->notify_emails);
    }
}
