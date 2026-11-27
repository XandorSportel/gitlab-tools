@extends('layouts.app')

@section('title', 'Standalone Tasks — GitLab Tools')
@section('header', 'Standalone Tasks')

@section('content')
    @if (session('status'))
        <div class="alert success" role="status">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4">
        <section class="card" aria-labelledby="open-tasks">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="section-title" id="open-tasks">Open tasks</h2>
                @can('create planning tasks')
                    <a class="button" href="{{ route('planning.tasks.create') }}">Add task</a>
                @endcan
            </div>

            @forelse ($openTasks as $task)
                @include('planning.task-card')
            @empty
                <p class="muted mt-3 text-sm">No open tasks.</p>
            @endforelse
        </section>

        <section class="card" aria-labelledby="completed-tasks">
            <details open>
                <summary class="cursor-pointer">
                    <h2 class="section-title inline" id="completed-tasks">Completed tasks ({{ $completedTasks->total() }})</h2>
                </summary>

                @forelse ($completedTasks as $task)
                    @include('planning.task-card')
                @empty
                    <p class="muted mt-3 text-sm">No completed tasks.</p>
                @endforelse

                @include('planning.task-pagination', ['tasks' => $completedTasks, 'label' => 'Completed tasks pages'])
            </details>
        </section>
    </div>
@endsection
