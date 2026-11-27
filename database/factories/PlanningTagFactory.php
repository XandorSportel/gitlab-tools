<?php

namespace Database\Factories;

use App\Models\PlanningTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanningTag> */
class PlanningTagFactory extends Factory
{
    /** @return array{name: string, name_key: string} */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return ['name' => $name, 'name_key' => PlanningTag::nameKey($name)];
    }
}
