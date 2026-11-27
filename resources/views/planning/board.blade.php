@extends('layouts.app')

@section('title', 'Planning — GitLab Tools')
@section('header', 'Sprint Issues')

@section('content')
    @if (session('status'))
        <div class="alert success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($error)
        <div class="alert" role="alert">{{ $error }}</div>
    @endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-semibold">Sprint issues</h2>
        <a class="button secondary" href="{{ route('settings') }}#planning-settings">Planning settings</a>
    </div>

    @if (! $milestoneSelected)
        <p class="card muted">Choose a current sprint in Planning settings to see its open issues.</p>
    @elseif (! $error)
        <form class="card mb-5 flex flex-wrap items-end gap-3" method="get" action="{{ route('planning.index') }}">
            <div class="min-w-0 flex-1 basis-56">
                <label for="planning-search">Search issues or tasks</label>
                <input id="planning-search" name="q" type="search" value="{{ $search }}" maxlength="255" placeholder="Title or issue ID, e.g. #42">
            </div>
            <div class="min-w-0 flex-1 basis-40">
                <label for="planning-tag">Tag</label>
                <select id="planning-tag" name="tag">
                    <option value="">All tags</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag }}" @selected(mb_strtolower($selectedTag) === mb_strtolower($tag))>{{ $tag }}</option>
                    @endforeach
                </select>
            </div>
            <button class="primary" type="submit">Filter</button>
            @if ($search !== '' || $selectedTag !== '')
                <a class="button secondary" href="{{ route('planning.index') }}">Clear</a>
            @endif
        </form>

        <div class="grid gap-4">
            @forelse ($cards as $card)
                @include('planning.issue-card', ['card' => $card, 'availableTags' => $availableTags])
            @empty
                <p class="card muted">{{ $search !== '' || $selectedTag !== '' ? 'No issues match these filters.' : 'No open issues in this sprint.' }}</p>
            @endforelse
        </div>

        {{ $cards->onEachSide(1)->links('planning.issue-pagination') }}

        @canany(['edit planning tasks', 'delete planning tasks'])
            <p class="muted mt-4 text-xs">Uicons by <a href="https://www.flaticon.com/uicons">Flaticon</a></p>
        @endcanany
    @endif
@endsection
