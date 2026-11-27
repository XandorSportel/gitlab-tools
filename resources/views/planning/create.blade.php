@extends('layouts.app')

@section('title', 'Add task — GitLab Tools')
@section('header', $gitLabIssue ? 'Sprint Issues' : 'Standalone Tasks')

@section('content')
    <section class="card max-w-2xl" aria-labelledby="add-task">
        <h2 class="section-title" id="add-task">Add task</h2>
        @if ($gitLabIssue)
            <p class="section-copy">#{{ $gitLabIssue['iid'] }} {{ $gitLabIssue['title'] }}</p>
        @endif

        @include('planning.task-form', [
            'action' => $gitLabIssue ? route('planning.store', $gitLabIssue['id']) : route('planning.tasks.store'),
            'task' => null,
            'selectedAssigneeIds' => [],
            'cancelRoute' => $gitLabIssue ? 'planning.index' : 'planning.tasks.index',
        ])
    </section>
@endsection
