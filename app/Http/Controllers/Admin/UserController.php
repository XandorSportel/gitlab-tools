<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(AdminAccess $access): View
    {
        $users = User::query()->with(['roles.permissions', 'permissions'])->orderBy('name')->orderBy('id')->paginate(25);

        return view('admin.users', compact('users', 'access'));
    }

    public function create(): View
    {
        return view('admin.user-form', ['user' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::create($this->validateUser($request));

        return redirect()->route('admin.users.edit', $user)->with('status', 'User created.');
    }

    public function edit(Request $request, User $user, AdminAccess $access): View
    {
        abort_unless($access->canManageUser($request->user(), $user), 403);

        $canAssignAccess = $request->user()->can('admin permissions');
        $roles = $canAssignAccess ? $access->roles($request->user()) : collect();
        $permissions = $canAssignAccess ? $access->permissions($request->user()) : collect();

        return view('admin.user-form', compact('user', 'canAssignAccess', 'roles', 'permissions'));
    }

    public function update(Request $request, User $user, AdminAccess $access): RedirectResponse
    {
        abort_unless($access->canManageUser($request->user(), $user), 403);

        $validated = $this->validateUser($request, $user);

        DB::transaction(function () use ($request, $user, $access, $validated): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($access->canManageUser($request->user(), $lockedUser), 403);
            $lockedUser->fill($validated);

            if ($lockedUser->isDirty('email')) {
                $lockedUser->email_verified_at = null;
            }

            if ($lockedUser->isDirty('password')) {
                $lockedUser->setRememberToken(null);
                DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
            }

            $lockedUser->save();
        });

        return redirect()->route('admin.users.edit', $user)->with('status', 'User updated.');
    }

    public function updateAccess(Request $request, User $user, AdminAccess $access): RedirectResponse
    {
        abort_unless($access->canManageUser($request->user(), $user), 403);

        $roles = $access->roles($request->user());
        $permissions = $access->permissions($request->user());
        $validated = $request->validate([
            ...$access->assignmentRules('roles', $roles),
            ...$access->assignmentRules('permissions', $permissions),
        ]);

        DB::transaction(function () use ($request, $user, $access, $validated, $roles, $permissions): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($access->canManageUser($request->user(), $lockedUser), 403);
            $lockedUser->syncRoles($roles->whereIn('id', $validated['roles'] ?? []));
            $lockedUser->syncPermissions($permissions->whereIn('id', $validated['permissions'] ?? []));
        });

        return redirect()->route('admin.users.edit', $user)->with('status', 'User access updated.');
    }

    public function destroy(Request $request, User $user, AdminAccess $access): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $access): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($access->canManageUser($request->user(), $lockedUser), 403);
            DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
            $lockedUser->delete();
        });

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    /** @return array{name: string, email: string, password?: string} */
    private function validateUser(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! isset($validated['password'])) {
            unset($validated['password']);
        }

        return $validated;
    }
}
