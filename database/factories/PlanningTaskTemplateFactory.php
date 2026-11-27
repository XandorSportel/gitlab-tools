<?php

namespace Database\Factories;

use App\Models\PlanningTaskTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningTaskTemplate>
 */
class PlanningTaskTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
        ];
    }
}
