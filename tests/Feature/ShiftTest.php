<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_cashier_opens_one_shift_at_a_time_with_a_non_negative_float(): void
    {
        $cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun']);

        $this->actingAs($cashier)
            ->postJson(route('shifts.store'), ['opening_float' => -1])
            ->assertJsonValidationErrors(['opening_float' => "The opening float can't be negative."]);

        $this->actingAs($cashier)
            ->postJson(route('shifts.store'), ['opening_float' => 5000, 'note' => 'Morning shift'])
            ->assertCreated()
            ->assertJsonPath('shift.shift_no', 'SHIFT-00001');

        $shift = Shift::query()->sole();
        $this->assertSame(['open', '5000.00', 'Morning shift', 'Kasun', 0], [
            $shift->status, $shift->opening_float, $shift->open_note, $shift->cashier_name, $shift->sales_count,
        ]);

        $this->actingAs($cashier)
            ->postJson(route('shifts.store'), ['opening_float' => 1000])
            ->assertJsonValidationErrors(['shift' => 'You already have an open shift — close it before opening a new one.']);
    }

    public function test_closing_works_out_expected_cash_and_variance_from_cash_legs_and_cash_expenses(): void
    {
        $cashier = User::factory()->create(['role' => 'Cashier']);
        $product = Product::factory()->create(['selling_price' => 2000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 5, 1000, location: 'showroom');

        $this->actingAs($cashier)->postJson(route('shifts.store'), ['opening_float' => 5000])->assertCreated();

        // Cash sale 2,000 + the cash leg (1,000) of a Cash + Card split; card money never counts as drawer cash.
        $this->actingAs($cashier)->postJson(route('sales.store'), [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash']],
            'expected_total' => 2000,
        ])->assertCreated();
        $this->actingAs($cashier)->postJson(route('sales.store'), [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 1000], ['method' => 'card']],
            'expected_total' => 2000,
        ])->assertCreated();

        $shift = Shift::query()->sole();
        $shift->update(['cash_expenses_total' => 300]);

        $this->actingAs(User::factory()->create(['role' => 'Cashier']))
            ->postJson(route('shifts.close', $shift), ['counted_cash' => 7600])
            ->assertJsonValidationErrors(['shift' => 'Only the cashier who opened this shift can close it.']);

        // Expected = 5,000 + 3,000 − 300 = 7,700; counted 7,600 → 100 short.
        $this->actingAs($cashier)
            ->postJson(route('shifts.close', $shift), ['counted_cash' => 7600, 'note' => 'Rs. 100 short — coin drawer miscount'])
            ->assertOk()
            ->assertExactJson([
                'message' => 'SHIFT-00001 is closed.',
                'result' => ['shift_no' => 'SHIFT-00001', 'expected_cash' => 7700, 'counted_cash' => 7600, 'variance' => -100],
            ]);

        $shift->refresh();
        $this->assertSame(['closed', '3000.00', '1000.00', '7700.00', '7600.00', '-100.00', 'pending', false, $cashier->id], [
            $shift->status, $shift->cash_sales_total, $shift->card_sales_total, $shift->expected_cash, $shift->counted_cash,
            $shift->variance, $shift->review_status, $shift->force_closed, $shift->closed_by_id,
        ]);
        $this->assertNotNull($shift->closed_at);

        $this->actingAs($cashier)
            ->postJson(route('shifts.close', $shift), ['counted_cash' => 7700])
            ->assertJsonValidationErrors(['shift' => 'SHIFT-00001 is already closed.']);

        $this->actingAs($cashier)
            ->postJson(route('sales.store'), [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payments' => [['method' => 'cash']],
                'expected_total' => 2000,
            ])
            ->assertJsonValidationErrors(['shift' => 'Your shift is not open — reopen a shift before selling.']);
    }

    public function test_an_exact_count_has_zero_variance(): void
    {
        $cashier = User::factory()->create(['role' => 'Cashier']);
        $this->actingAs($cashier)->postJson(route('shifts.store'), ['opening_float' => 2500])->assertCreated();

        $this->actingAs($cashier)
            ->postJson(route('shifts.close', Shift::query()->sole()), ['counted_cash' => 2500])
            ->assertOk()
            ->assertJsonPath('result.variance', 0);
    }
}
