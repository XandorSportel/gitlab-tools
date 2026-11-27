<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\AdminAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $identity = Validator::make(config('admin'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ])->validate();

        DB::transaction(function () use ($identity): void {
            $user = User::query()->where('email', $identity['email'])->lockForUpdate()->first();

            if ($user && ! $user->hasRole(AdminAccess::Administrator, 'web')) {
                throw new RuntimeException('ADMIN_EMAIL already belongs to an account without the administrator role. Choose a new email address.');
            }

            if (! $user) {
                $credentials = Validator::make(config('admin'), [
                    'password' => ['required', 'string', Password::min(12)->letters()->numbers()],
                ])->validate();
                $user = User::create([...$identity, ...$credentials]);
            }

            $this->call(PlanningPermissionSeeder::class);
            $adminPermissions = array_map(
                fn (string $name) => Permission::findOrCreate($name, 'web'),
                array_column(AdminAccess::Pages, 'permission'),
            );

            $adminRole = Role::findOrCreate(AdminAccess::Administrator, 'web');
            $adminRole->givePermissionTo(...$adminPermissions, ...PlanningPermissionSeeder::Permissions);
            $user->assignRole($adminRole);
        });
    }
}
