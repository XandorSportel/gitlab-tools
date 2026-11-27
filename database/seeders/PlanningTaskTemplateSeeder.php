<?php

namespace Database\Seeders;

use App\Models\PlanningTaskTemplate;
use Illuminate\Database\Seeder;

class PlanningTaskTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'Design sessie uitvoeren',
            'Testen Windows',
            'Testen Linux',
            'Testen MacOS',
            'Testen Android',
            'Testen iOS',
        ] as $title) {
            PlanningTaskTemplate::query()->firstOrCreate(['title' => $title]);
        }
    }
}
