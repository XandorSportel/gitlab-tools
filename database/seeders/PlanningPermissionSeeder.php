<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PlanningPermissionSeeder extends Seeder
{
    public const array Permissions = [
        'view planning',
        'create planning tasks',
        'edit planning tasks',
        'delete planning tasks',
        'manage planning tags',
    ];

    public function run(): void
    {
        foreach (self::Permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
