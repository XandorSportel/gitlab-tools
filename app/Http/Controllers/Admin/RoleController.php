<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(AdminAccess $access): View
    {
        $roles = Role::query()->where('guard_name', 'web')->with('permissions')->orderBy('name')->paginate(25);

        return view('admin.roles', compact('roles', 'access'));
    }

    public function create(Request $request, AdminAccess $access): View
    {
        return view('admin.role-form', ['role' => null, 'permissions' => $access->permissions($request->user())]);
    }

    public function store(Request $request, AdminAccess $access): RedirectResponse
    {
        return $this->save($request, $access, new Role(['guard_name' => 'web']));
    }

    public function edit(Request $request, Role $role, AdminAccess $access): View
    {
        abort_unless($access->canManageRole($request->user(), $role), 403);

        return view('admin.role-form', ['role' => $role, 'permissions' => $access->permissions($request->user())]);
    }

    public function update(Request $request, Role $role, AdminAccess $access): RedirectResponse
    {
        abort_unless($access->canManageRole($request->user(), $role), 403);

        return $this->save($request, $access, $role);
    }

    public function destroy(Request $request, Role $role, AdminAccess $access): RedirectResponse
    {
        abort_unless($access->canManageRole($request->user(), $role), 403);

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }

    private function save(Request $request, AdminAccess $access, Role $role): RedirectResponse
    {
        $permissions = $access->permissions($request->user());
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:125',
                Rule::unique(Role::class)->where('guard_name', 'web')->ignore($role->id)],
            ...$access->assignmentRules('permissions', $permissions),
        ]);

        if (mb_strtolower($validated['name']) === AdminAccess::Administrator) {
            throw ValidationException::withMessages(['name' => 'This role name is reserved.']);
        }

        DB::transaction(function () use ($role, $validated, $permissions): void {
            $role->name = $validated['name'];
            $role->save();
            $role->syncPermissions($permissions->whereIn('id', $validated['permissions'] ?? []));
        });

        return redirect()->route('admin.roles.index')->with('status', 'Role saved.');
    }
}
