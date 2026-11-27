<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminAccess
{
    public const string Administrator = 'administrator';

    public const array Pages = [
        'users' => ['label' => 'Users', 'permission' => 'admin users'],
        'roles' => ['label' => 'Roles', 'permission' => 'admin roles'],
        'permissions' => ['label' => 'Permissions', 'permission' => 'admin permissions'],
        'tags' => ['label' => 'Planning tags', 'permission' => 'admin planning tags'],
        'task-templates' => ['label' => 'Standard tasks', 'permission' => 'admin planning task templates'],
    ];

    public function canManageUser(User $actor, User $user): bool
    {
        return ! $actor->is($user)
            && ! $user->hasRole(self::Administrator, 'web')
            && $this->holdsPermissions($actor, $user->getAllPermissions());
    }

    public function canManageRole(User $actor, Role $role): bool
    {
        return $role->guard_name === 'web'
            && $role->name !== self::Administrator
            && ! $actor->hasRole($role)
            && $this->holdsPermissions($actor, $role->permissions);
    }

    /** @return Collection<int, Permission> */
    public function permissions(User $actor): Collection
    {
        if ($actor->hasRole(self::Administrator, 'web')) {
            return Permission::query()->where('guard_name', 'web')->orderBy('name')->get();
        }

        return $actor->getAllPermissions()->where('guard_name', 'web')->sortBy('name')->values();
    }

    /** @return Collection<int, Role> */
    public function roles(User $actor): Collection
    {
        return Role::query()->where('guard_name', 'web')->where('name', '!=', self::Administrator)
            ->with('permissions')->orderBy('name')->get()
            ->filter(fn (Role $role): bool => $this->holdsPermissions($actor, $role->permissions))
            ->values();
    }

    /** @param Collection<int, Permission> $permissions */
    private function holdsPermissions(User $actor, Collection $permissions): bool
    {
        return $actor->hasRole(self::Administrator, 'web')
            || $permissions->pluck('id')->diff($this->permissions($actor)->pluck('id'))->isEmpty();
    }

    /** @return array<string, array> */
    public function assignmentRules(string $field, Collection $options): array
    {
        return [
            $field => ['sometimes', 'array', 'list'],
            $field.'.*' => ['required', 'integer', 'distinct', Rule::in($options->modelKeys())],
        ];
    }
}
