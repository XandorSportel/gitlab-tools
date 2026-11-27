<?php

namespace Database\Factories;

use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanningTask> */
class PlanningTaskFactory extends Factory
{
    /** @return array{title: string, description: string, created_by: Factory<User>} */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
