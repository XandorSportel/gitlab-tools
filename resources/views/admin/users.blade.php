@extends('admin.layout')

@section('admin-content')
    <section class="card" aria-labelledby="users-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title" id="users-title">Users</h2>
            <a class="button" href="{{ route('admin.users.create') }}">Add user</a>
        </div>
        <ul class="divide-y divide-zinc-200">
            @foreach ($users as $user)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 [overflow-wrap:anywhere]">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $user->name }}</p>
                        <p class="muted text-sm">{{ $user->email }}</p>
                        @if ($user->roles->isNotEmpty())
                            <p class="muted mt-1 text-sm">{{ $user->roles->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                    @if ($access->canManageUser(auth()->user(), $user))
                        <a class="button secondary" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                    @endif
                </li>
            @endforeach
        </ul>
        {{ $users->links('components.pagination') }}
    </section>
@endsection
