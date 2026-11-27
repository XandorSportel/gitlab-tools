<?php

namespace Database\Seeders;

use App\Models\PlanningTask;
use Illuminate\Database\Seeder;

class PlanningTaskSeeder extends Seeder
{
    public function run(): void
    {
        PlanningTask::factory()->count(3)->create();
    }
}
