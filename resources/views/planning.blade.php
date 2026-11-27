@extends('layouts.app')

@section('title', 'Planning — GitLab Tools')
@section('header', 'Planning')

@section('content')
    @if (session('status'))
        <div class="alert success" role="status">{{ session('status') }}</div>
    @endif

    <div @class(['grid items-start gap-5', 'xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]' => auth()->user()->can('create planning tasks')])>
        <div class="grid min-w-0 gap-5">
            <section class="card min-w-0" aria-labelledby="open-tasks">
                <h2 class="section-title" id="open-tasks">Open tasks</h2>

                @forelse ($openTasks as $task)
                    @include('planning.task-card', ['task' => $task])
                @empty
                    <p class="muted mt-3 text-sm">No open tasks.</p>
                @endforelse

                @include('planning.task-pagination', ['tasks' => $openTasks, 'label' => 'Open task pages'])
            </section>

            <details class="card min-w-0" id="completed-tasks" @if (request()->query->has('completed_page')) open @endif>
                <summary class="cursor-pointer text-base font-semibold">Completed tasks</summary>

                @forelse ($completedTasks as $task)
                    @include('planning.task-card', ['task' => $task])
                @empty
                    <p class="muted mt-3 text-sm">No completed tasks.</p>
                @endforelse

                @include('planning.task-pagination', ['tasks' => $completedTasks, 'label' => 'Completed task pages'])
            </details>
        </div>

        @can('create planning tasks')
            <section class="card min-w-0" aria-labelledby="add-task">
                <h2 class="section-title" id="add-task">Add a task</h2>

                @include('planning.task-form', [
                    'action' => route('planning.store'),
                    'task' => null,
                    'selectedAssigneeIds' => [],
                ])
            </section>
        @endcan
    </div>
@endsection
