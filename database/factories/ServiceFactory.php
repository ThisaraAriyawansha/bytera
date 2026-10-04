<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'default_price' => fake()->randomFloat(2, 500, 20000),
            'description' => fake()->optional()->sentence(),
            'custom_fields' => [],
            'active' => true,
        ];
    }

    /**
     * Indicate that the service is hidden from the POS.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
