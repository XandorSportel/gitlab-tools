<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PlanningPermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate('view planning', 'web');
        Permission::findOrCreate('create planning tasks', 'web');
        Permission::findOrCreate('edit planning tasks', 'web');
        Permission::findOrCreate('delete planning tasks', 'web');
    }
}
