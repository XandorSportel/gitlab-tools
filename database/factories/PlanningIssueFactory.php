<?php

namespace Database\Factories;

use App\Models\PlanningIssue;
use App\Services\PlanningSprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanningIssue> */
class PlanningIssueFactory extends Factory
{
    /** @return array{gitlab_instance: string, gitlab_issue_id: int} */
    public function definition(): array
    {
        return [
            'gitlab_instance' => app(PlanningSprint::class)->instance(),
            'gitlab_issue_id' => fake()->unique()->numberBetween(100, 1000000),
        ];
    }
}
