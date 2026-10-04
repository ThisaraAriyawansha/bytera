<?php

namespace Database\Factories;

use App\Models\MainCategory;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubCategory>
 */
class SubCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'main_category_id' => MainCategory::factory(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
