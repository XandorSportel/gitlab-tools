@extends('layouts.app')

@section('title', 'Edit task — GitLab Tools')
@section('header', 'Planning')

@section('content')
    <section class="card max-w-2xl" aria-labelledby="edit-task">
        <h2 class="section-title" id="edit-task">Edit task</h2>

        @include('planning.task-form', ['action' => route('planning.update', $task)])
    </section>
@endsection
