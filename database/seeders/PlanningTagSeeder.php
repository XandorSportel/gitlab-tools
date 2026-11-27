<?php

namespace Database\Seeders;

use App\Models\PlanningTag;
use Illuminate\Database\Seeder;

class PlanningTagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Design Session', 'Requires testing'] as $name) {
            PlanningTag::query()->firstOrCreate(['name_key' => PlanningTag::nameKey($name)], ['name' => $name]);
        }
    }
}
