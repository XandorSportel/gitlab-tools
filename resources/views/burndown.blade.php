@extends('layouts.app')
@section('title', 'Burndown charts — GitLab Tools')
@section('header', 'Burndown charts')
@section('content')
    <div class="page-head"><div><h2>Milestones</h2><p>Select a milestone to generate its burndown chart.</p></div><span class="badge">{{ config('services.gitlab.project') ?: 'Project not configured' }}</span></div>
    @if(isset($error)) <div class="alert">{{ $error }}</div> @endif
    @if($errors->any()) <div class="alert">{{ $errors->first() }}</div> @endif
        <section class="card"><div class="milestones">
                @forelse($milestones as $milestone)
                    <a class="milestone" href="{{ route('burndown.show', $milestone['id']) }}">
                        <span><strong>{{ $milestone['title'] }}</strong><br><small class="muted">{{ $milestone['start_date'] ?: 'No start date' }} → {{ $milestone['due_date'] ?: 'No due date' }}</small></span>
                        <span class="badge">{{ $milestone['state'] }}</span>
                    </a>
                @empty
                    <div class="empty">No milestones found in this project.</div>
                @endforelse
        </div></section>
@endsection
