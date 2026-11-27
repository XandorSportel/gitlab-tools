@extends('layouts.app')

@section('title', 'Add task — GitLab Tools')
@section('header', 'Sprint Issues')

@section('content')
    <section class="card max-w-2xl" aria-labelledby="add-task">
        <h2 class="section-title" id="add-task">Add task</h2>
        <p class="section-copy">#{{ $gitLabIssue['iid'] }} {{ $gitLabIssue['title'] }}</p>

        @include('planning.task-form', [
            'action' => route('planning.store', $gitLabIssue['id']),
            'task' => null,
            'selectedAssigneeIds' => [],
            'showCancel' => true,
        ])
    </section>
@endsection
