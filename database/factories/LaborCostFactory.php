<?php

namespace Database\Factories;

use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaborCost>
 */
class LaborCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_date' => fake()->dateTimeBetween('-1 year'),
            'staff_id' => Staff::factory(),
            'order_no' => fake()->bothify('??####-N##'),
            'work_hours' => fake()->numberBetween(0, 8),
            'work_minutes' => fake()->randomElement([0, 15, 30, 45]),
            'is_overtime' => false,
            'position_weight_cache' => 1,
            'is_provisional' => false,
        ];
    }
}
