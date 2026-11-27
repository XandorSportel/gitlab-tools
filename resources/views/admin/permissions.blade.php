@extends('admin.layout')

@section('admin-content')
    <section class="card" aria-labelledby="permissions-title">
        <h2 class="section-title" id="permissions-title">Permissions</h2>
        <form class="my-4 flex flex-wrap items-end gap-2" method="post" action="{{ route('admin.permissions.store') }}">
            @csrf
            <div class="min-w-0 flex-1 basis-56">
                <label for="permission-name">Permission name</label>
                <input id="permission-name" name="name" maxlength="125" required>
            </div>
            <button class="primary" type="submit">Add permission</button>
        </form>
        <ul class="divide-y divide-zinc-200">
            @foreach ($permissions as $permission)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 [overflow-wrap:anywhere]">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $permission->name }}</p>
                        <p class="muted text-sm">{{ $permission->roles_count }} roles · {{ $permission->users_count }} direct users</p>
                    </div>
                    @if (in_array($permission->name, $builtIn, true))
                        <span class="muted text-sm">Built in</span>
                    @elseif (! $permission->roles_count && ! $permission->users_count)
                        <form class="flex min-w-0 flex-wrap items-center gap-2" method="post" action="{{ route('admin.permissions.update', $permission) }}">
                            @csrf
                            @method('PATCH')
                            <label class="sr-only" for="permission-{{ $permission->id }}">Rename {{ $permission->name }}</label>
                            <input class="min-w-0 flex-1 basis-40" id="permission-{{ $permission->id }}" name="name" value="{{ $permission->name }}" maxlength="125" required>
                            <button class="button secondary" type="submit">Rename</button>
                        </form>
                        <x-admin-delete :id="'delete-permission-'.$permission->id" :action="route('admin.permissions.destroy', $permission)" title="Delete permission?" :description="'The permission '.$permission->name.' will be permanently deleted.'" />
                    @endif
                </li>
            @endforeach
        </ul>
        {{ $permissions->links() }}
    </section>
@endsection
