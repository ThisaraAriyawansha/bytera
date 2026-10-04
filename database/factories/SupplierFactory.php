<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state: a supplier we owe nothing.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->numerify('07########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'total_payable' => 0,
            'amount_paid' => 0,
            'balance' => 0,
            'payment_status' => 'paid',
        ];
    }

    /**
     * Indicate that we owe the supplier the given amount and have paid part of it.
     */
    public function owing(float $totalPayable, float $amountPaid = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'total_payable' => $totalPayable,
            'amount_paid' => $amountPaid,
            'balance' => $totalPayable - $amountPaid,
            'payment_status' => match (true) {
                $totalPayable - $amountPaid <= 0 => 'paid',
                $amountPaid <= 0 => 'outstanding',
                default => 'partial',
            },
        ]);
    }
}
