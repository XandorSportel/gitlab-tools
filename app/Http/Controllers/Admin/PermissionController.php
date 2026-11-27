<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAccess;
use Database\Seeders\PlanningPermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::query()->where('guard_name', 'web')->withCount(['roles', 'users'])->orderBy('name')->paginate(25);
        $builtIn = $this->builtIn();

        return view('admin.permissions', compact('permissions', 'builtIn'));
    }

    public function store(Request $request): RedirectResponse
    {
        $permission = new Permission(['guard_name' => 'web']);
        $permission->name = $this->validateName($request, $permission);
        $permission->save();

        return redirect()->route('admin.permissions.index')->with('status', 'Permission created.');
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $this->authorizeCustomPermission($permission);
        if ($this->isAssigned($permission)) {
            return back()->withErrors(['permission' => 'Remove this permission from its users and roles before renaming it.']);
        }

        $permission->name = $this->validateName($request, $permission);
        $permission->save();

        return redirect()->route('admin.permissions.index')->with('status', 'Permission renamed.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $this->authorizeCustomPermission($permission);
        if ($this->isAssigned($permission)) {
            return back()->withErrors(['permission' => 'Remove this permission from its users and roles first.']);
        }

        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('status', 'Permission deleted.');
    }

    private function validateName(Request $request, Permission $permission): string
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::notIn($this->builtIn()),
                Rule::unique(Permission::class)->where('guard_name', 'web')->ignore($permission->id)],
        ])['name'];
    }

    private function authorizeCustomPermission(Permission $permission): void
    {
        abort_unless($permission->guard_name === 'web' && ! in_array($permission->name, $this->builtIn(), true), 403);
    }

    private function isAssigned(Permission $permission): bool
    {
        return $permission->roles()->exists() || $permission->users()->exists();
    }

    /** @return list<string> */
    private function builtIn(): array
    {
        return [...PlanningPermissionSeeder::Permissions, ...array_column(AdminAccess::Pages, 'permission')];
    }
}
