<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_uses_a_prefix_search_on_name_or_phone(): void
    {
        Customer::factory()->create(['name' => 'Kasun Perera', 'phone' => '0771234567', 'loyalty_points' => 125]);
        Customer::factory()->create(['name' => 'Nimal Kasun', 'phone' => '0719999999']);
        Customer::factory()->create(['name' => 'Amal Silva', 'phone' => '0701111111', 'phone2' => '0775550000']);

        $user = User::factory()->create(['role' => 'Cashier']);

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('3 customers in total')
            ->assertSee('125 pts');

        $this->actingAs($user)->get(route('customers.index', ['search' => 'kas']))
            ->assertSee('Kasun Perera')->assertDontSee('Nimal Kasun')->assertSee('1 customer found');

        $this->actingAs($user)->get(route('customers.index', ['search' => '0775']))
            ->assertSee('Amal Silva')->assertDontSee('Kasun Perera');
    }

    public function test_customer_can_be_added_and_edited_without_touching_points(): void
    {
        $user = User::factory()->create(['role' => 'Cashier']);

        $this->actingAs($user)
            ->postJson(route('customers.store'), [
                'name' => 'Kasun Perera',
                'phone' => '0771234567',
                'phone2' => '',
                'email' => 'Kasun@Example.com',
                'address' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('customer.name', 'Kasun Perera');

        $customer = Customer::query()->sole();
        $this->assertSame('kasun@example.com', $customer->email);
        $this->assertNull($customer->phone2);
        $this->assertSame(0, $customer->loyalty_points);

        $customer->update(['loyalty_points' => 40]);

        $this->actingAs($user)
            ->putJson(route('customers.update', $customer), [
                'name' => 'Kasun P.',
                'phone' => '0771234567',
                'phone2' => '0112345678',
                'loyalty_points' => 9999,
            ])
            ->assertOk();

        $customer->refresh();
        $this->assertSame('Kasun P.', $customer->name);
        $this->assertSame('0112345678', $customer->phone2);
        $this->assertNull($customer->email);
        $this->assertSame(40, $customer->loyalty_points);
    }

    public function test_name_phone_and_a_valid_email_are_required(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Cashier']))
            ->postJson(route('customers.store'), ['name' => '', 'phone' => '', 'email' => 'a@b.com, c@d.com'])
            ->assertJsonValidationErrors(['name', 'phone', 'email' => 'Enter a valid email address.']);
    }

    public function test_search_endpoint_returns_at_most_eight_prefix_matches(): void
    {
        Customer::factory()->count(10)->sequence(fn ($sequence) => ['name' => "Kamal {$sequence->index}"])->create();
        Customer::factory()->create(['name' => 'Sunil', 'phone' => '0761112222', 'loyalty_points' => 30]);

        $user = User::factory()->create(['role' => 'Cashier']);

        $this->actingAs($user)->getJson(route('api.customers.search', ['q' => 'k']))
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->actingAs($user)->getJson(route('api.customers.search', ['q' => 'ka']))
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $this->actingAs($user)->getJson(route('api.customers.search', ['q' => '0761']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sunil')
            ->assertJsonPath('data.0.loyalty_points', 30);
    }

    public function test_search_endpoint_is_open_to_pos_and_jobs_users_only(): void
    {
        $noAccess = User::factory()->create([
            'role' => 'Staff',
            'permissions' => [...Permissions::defaults('Staff'), 'customers.view' => false, 'sales.view' => false, 'jobs.view' => false],
        ]);
        $posOnly = User::factory()->create([
            'role' => 'Cashier',
            'permissions' => [...Permissions::defaults('Cashier'), 'customers.view' => false, 'jobs.view' => false],
        ]);

        $this->actingAs($noAccess)->getJson(route('api.customers.search', ['q' => 'ka']))->assertForbidden();
        $this->actingAs($posOnly)->getJson(route('api.customers.search', ['q' => 'ka']))->assertOk();
        $this->actingAs($posOnly)->get(route('customers.index'))->assertForbidden();
    }

    public function test_search_endpoint_requires_login(): void
    {
        $this->getJson(route('api.customers.search', ['q' => 'ka']))->assertUnauthorized();
    }
}
