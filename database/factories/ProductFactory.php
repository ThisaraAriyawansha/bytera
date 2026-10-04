<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subCategory = SubCategory::factory();

        return [
            'name' => fake()->words(3, true),
            'brand_id' => Brand::factory(),
            'sub_category_id' => $subCategory,
            'main_category_id' => fn (array $attributes) => SubCategory::query()->findOrFail($attributes['sub_category_id'])->main_category_id,
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'barcode' => null,
            'selling_price' => fake()->randomFloat(2, 1000, 200000),
            'total_stock' => 0,
            'stores_stock' => 0,
            'showroom_stock' => 0,
            'low_stock_alert' => 5,
            'warranty_months' => 0,
            'track_serial' => false,
            'active' => true,
        ];
    }
}
