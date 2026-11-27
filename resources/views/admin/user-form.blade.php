@extends('admin.layout')

@section('admin-content')
    <div class="grid max-w-2xl gap-5">
        <section class="card">
            <h2 class="section-title">{{ $user ? 'Edit user' : 'Add user' }}</h2>
            <form class="mt-4 grid gap-4" method="post" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
                @csrf
                @if ($user)
                    @method('PATCH')
                @endif
                <div>
                    <label for="name">Name</label>
                    <input id="name" name="name" value="{{ is_string(old('name', $user?->name)) ? old('name', $user?->name) : '' }}" maxlength="255" required>
                </div>
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ is_string(old('email', $user?->email)) ? old('email', $user?->email) : '' }}" maxlength="255" required>
                </div>
                <div>
                    <label for="password">{{ $user ? 'New password (optional)' : 'Password' }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" @required(! $user)>
                </div>
                <div>
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $user)>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="primary" type="submit">Save user</button>
                    <a class="button secondary" href="{{ route('admin.users.index') }}">Back to users</a>
                </div>
            </form>
        </section>

        @if ($user && $canAssignAccess)
            <section class="card">
                <h2 class="section-title">User access</h2>
                <form class="mt-4 grid gap-4" method="post" action="{{ route('admin.users.access', $user) }}">
                    @csrf
                    @method('PATCH')
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold">Roles</legend>
                        @forelse ($roles as $role)
                            <label class="flex items-center gap-2">
                                <input class="w-auto!" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, (array) old('roles', $user->roles->modelKeys())))>
                                {{ $role->name }}
                            </label>
                        @empty
                            <p class="muted text-sm">No assignable roles.</p>
                        @endforelse
                    </fieldset>
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold">Direct permissions</legend>
                        @foreach ($permissions as $permission)
                            <label class="flex items-center gap-2">
                                <input class="w-auto!" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, (array) old('permissions', $user->permissions->modelKeys())))>
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </fieldset>
                    <div><button class="primary" type="submit">Save access</button></div>
                </form>
            </section>
        @endif

        @if ($user)
            <div>
                <x-admin-delete id="delete-user" :action="route('admin.users.destroy', $user)" title="Delete user?" :description="'The account for '.$user->name.' will be permanently deleted.'" />
            </div>
        @endif
    </div>
@endsection
