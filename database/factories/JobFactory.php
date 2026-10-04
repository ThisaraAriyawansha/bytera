<?php

namespace Database\Factories;

use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receivedBy = User::factory();

        return [
            'job_no' => 'JOB-'.fake()->unique()->numerify('9####'),
            'customer_name' => fake()->name(),
            'customer_company' => '',
            'customer_address' => fake()->address(),
            'customer_city' => fake()->city(),
            'customer_phone' => fake()->numerify('07########'),
            'customer_phone2' => '',
            'customer_email' => '',
            'device_type' => 'Laptop',
            'device_type_other' => '',
            'brand' => fake()->company(),
            'model' => fake()->bothify('??-###'),
            'serial_no' => fake()->bothify('SN#######'),
            'color' => fake()->safeColorName(),
            'parts' => [],
            'fault_description' => fake()->sentence(),
            'accessories' => [],
            'accessories_other' => '',
            'physical_condition' => [],
            'special_notes' => '',
            'received_by_id' => $receivedBy,
            'received_by_name' => fake()->name(),
            'assigned_technician_name' => '',
            'services' => [],
            'estimated_cost' => fake()->randomFloat(2, 1000, 50000),
            'advance_paid' => 0,
            'status' => 'pending',
        ];
    }
}
