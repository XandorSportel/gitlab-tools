<?php

namespace Database\Seeders;

use App\Models\PlanningIssue;
use App\Models\PlanningTask;
use Illuminate\Database\Seeder;

class PlanningIssueSeeder extends Seeder
{
    public function run(): void
    {
        PlanningIssue::factory()->has(PlanningTask::factory()->count(2), 'tasks')->create();
    }
}
