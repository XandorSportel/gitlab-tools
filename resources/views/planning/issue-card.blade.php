<article class="card min-w-0" id="issue-{{ $card['issue']['iid'] }}" aria-labelledby="issue-title-{{ $card['issue']['iid'] }}">
    <p @class([
        'mb-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
        'bg-green-50 text-green-800' => ! $card['needsAction'],
        'bg-amber-50 text-amber-900' => $card['needsAction'],
    ])>{{ $card['needsAction'] ? 'Action needed' : 'Ready to merge' }}</p>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <h3 class="section-title [overflow-wrap:anywhere]" id="issue-title-{{ $card['issue']['iid'] }}">
            <span class="muted">#{{ $card['issue']['iid'] }}</span>
            {{ $card['issue']['title'] }}
        </h3>
        <div class="flex flex-wrap gap-2">
            @if ($card['issue']['url'])
                <a class="button secondary" href="{{ $card['issue']['url'] }}" target="_blank" rel="noopener noreferrer">Open issue in GitLab</a>
            @endif
            @can('create planning tasks')
                <a class="button" href="{{ route('planning.create', $card['issue']['id']) }}">Add task</a>
            @endcan
        </div>
    </div>

    @if ($card['tags'])
        <ul class="mt-3 flex flex-wrap gap-2" aria-label="Issue tags">
            @foreach ($card['tags'] as $tag)
                <li><a class="badge inline-block bg-blue-100! text-blue-800! no-underline hover:bg-blue-200! hover:underline" href="{{ route('planning.index', ['tag' => $tag]) }}">{{ $tag }}</a></li>
            @endforeach
        </ul>
    @endif

    @can('manage planning tags')
        <details class="mt-3">
            <summary class="muted cursor-pointer text-sm">Edit tags</summary>
            @if ($availableTags->isEmpty())
                <p class="muted mt-2 text-sm">No Planning tags have been created yet.</p>
            @else
                <form class="mt-2 grid gap-3" method="post" action="{{ route('planning.tags.update', $card['issue']['id']) }}">
                    @csrf
                    @method('PATCH')
                    <fieldset class="flex flex-wrap gap-x-4 gap-y-2">
                        <legend class="mb-2 text-sm font-semibold">Available tags</legend>
                        @foreach ($availableTags as $tag)
                            <label class="mb-0 flex cursor-pointer items-center gap-2">
                                <input class="w-auto! accent-blue-700" type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $card['tagIds'], true))>
                                {{ $tag->name }}
                            </label>
                        @endforeach
                    </fieldset>
                    <div><button class="button secondary" type="submit">Save tags</button></div>
                </form>
            @endif
        </details>
    @endcan

    @foreach ($card['openTasks'] as $task)
        @include('planning.task-card', ['task' => $task])
    @endforeach

    @if ($card['completedCount'])
        <details class="mt-4 border-t border-zinc-200 pt-4" @if ($card['showCompleted']) open @endif>
            <summary class="cursor-pointer text-sm font-semibold">Completed tasks ({{ $card['completedCount'] }})</summary>
            @foreach ($card['completedTasks'] as $task)
                @include('planning.task-card', ['task' => $task])
            @endforeach
            @include('planning.task-pagination', ['tasks' => $card['completedTasks'], 'label' => 'Completed task pages for issue #'.$card['issue']['iid']])
        </details>
    @endif
</article>
