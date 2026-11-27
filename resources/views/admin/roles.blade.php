@extends('admin.layout')

@section('admin-content')
    <section class="card" aria-labelledby="roles-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title" id="roles-title">Roles</h2>
            <a class="button" href="{{ route('admin.roles.create') }}">Add role</a>
        </div>
        <ul class="divide-y divide-zinc-200">
            @forelse ($roles as $role)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 [overflow-wrap:anywhere]">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $role->name }}</p>
                        <p class="muted text-sm">{{ $role->permissions->pluck('name')->join(', ') ?: 'No permissions' }}</p>
                    </div>
                    @if ($access->canManageRole(auth()->user(), $role))
                        <a class="button secondary" href="{{ route('admin.roles.edit', $role) }}">Edit</a>
                    @endif
                </li>
            @empty
                <li class="muted py-3">No roles yet.</li>
            @endforelse
        </ul>
        {{ $roles->links() }}
    </section>
@endsection
