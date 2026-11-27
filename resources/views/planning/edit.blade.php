@extends('layouts.app')

@section('title', 'Edit task — GitLab Tools')
@section('header', $task->planning_issue_id === null ? 'Standalone Tasks' : 'Sprint Issues')

@section('content')
    <section class="card max-w-2xl" aria-labelledby="edit-task">
        <h2 class="section-title" id="edit-task">Edit task</h2>

        @include('planning.task-form', [
            'action' => route('planning.update', $task),
            'showLinkField' => $task->planning_issue_id === null,
            'cancelRoute' => $task->planning_issue_id === null ? 'planning.tasks.index' : 'planning.index',
        ])
    </section>
@endsection
