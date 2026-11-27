@extends('admin.layout')

@section('admin-content')
    <section class="card max-w-2xl">
        <h2 class="section-title">{{ $role ? 'Edit role' : 'Add role' }}</h2>
        <form class="mt-4 grid gap-4" method="post" action="{{ $role ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
            @csrf
            @if ($role)
                @method('PATCH')
            @endif
            <div>
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ is_string(old('name', $role?->name)) ? old('name', $role?->name) : '' }}" maxlength="125" required>
            </div>
            <fieldset>
                <legend class="mb-2 text-sm font-semibold">Permissions</legend>
                @foreach ($permissions as $permission)
                    <label class="flex items-center gap-2">
                        <input class="w-auto!" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, (array) old('permissions', $role?->permissions->modelKeys() ?? [])))>
                        {{ $permission->name }}
                    </label>
                @endforeach
            </fieldset>
            <div class="flex flex-wrap gap-2">
                <button class="primary" type="submit">Save role</button>
                <a class="button secondary" href="{{ route('admin.roles.index') }}">Back to roles</a>
            </div>
        </form>
        @if ($role)
            <div class="mt-4">
                <x-admin-delete id="delete-role" :action="route('admin.roles.destroy', $role)" title="Delete role?" :description="'The role '.$role->name.' will be removed from all users who have it.'" />
            </div>
        @endif
    </section>
@endsection
