@extends('layouts.app')

@section('title', 'Add task — GitLab Tools')
@section('header', $gitLabIssue ? 'Sprint Issues' : 'Standalone Tasks')

@section('content')
    <section class="card max-w-2xl" aria-labelledby="add-task">
        <h2 class="section-title" id="add-task">Add task</h2>
        @if ($gitLabIssue)
            <p class="section-copy">#{{ $gitLabIssue['iid'] }} {{ $gitLabIssue['title'] }}</p>
        @endif

        @if ($taskTemplates->isNotEmpty())
            <details class="mb-4">
                <summary class="disclosure-summary cursor-pointer text-sm font-semibold"><i class="fi fi-rr-angle-right disclosure-icon" aria-hidden="true"></i> Choose a standard task</summary>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($taskTemplates as $template)
                        <li>
                            <a class="button secondary" href="{{ route('planning.create', ['issue' => $gitLabIssue['id'], 'template' => $template->id]) }}#title">{{ $template->title }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        @include('planning.task-form', [
            'suggestedTitle' => $suggestedTitle,
            'action' => $gitLabIssue ? route('planning.store', $gitLabIssue['id']) : route('planning.tasks.store'),
            'task' => null,
            'selectedAssigneeIds' => [],
            'showLinkField' => $gitLabIssue === null,
            'cancelRoute' => $gitLabIssue ? 'planning.index' : 'planning.tasks.index',
        ])
    </section>
@endsection
