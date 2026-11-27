<article class="mt-4 border-t border-zinc-200 pt-4 [overflow-wrap:anywhere]">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <h3 class="text-sm font-semibold">{{ $task->title }}</h3>
        @can('edit planning tasks')
            <form class="flex items-center gap-2" method="post" action="{{ route('planning.completion.update', $task) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="completed" value="0">
                <label class="mb-0 flex cursor-pointer items-center gap-2">
                    <input
                        class="w-auto! shrink-0 accent-orange-600 focus-visible:outline-2!"
                        type="checkbox"
                        name="completed"
                        value="1"
                        aria-label="Completed: {{ $task->title }}"
                        data-submit-on-change
                        @checked($task->completed_at !== null)
                    >
                    <span>Completed</span>
                </label>
                <noscript><button class="button secondary" type="submit">Save</button></noscript>
            </form>
        @endcan
    </div>
    @if ($task->completed_at)
        <p class="mt-2 text-sm text-green-700">
            Completed on <time datetime="{{ $task->completed_at->toDateString() }}">{{ $task->completed_at->format('d M Y') }}</time>
        </p>
    @endif
    @if ($task->description)
        <p class="mt-2 text-sm whitespace-pre-wrap">{{ $task->description }}</p>
    @endif
    <p class="muted mt-2 text-sm">
        Assigned to {{ $task->assignees->pluck('name')->join(', ') ?: 'no one' }}
    </p>
    @if ($task->creator)
        <p class="muted mt-1 text-xs">Added by {{ $task->creator->name }}</p>
    @endif
    @if ($task->gitlab_url || auth()->user()->canAny(['edit planning tasks', 'delete planning tasks']))
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($task->gitlab_url)
                <a class="button secondary" href="{{ $task->gitlab_url }}" target="_blank" rel="noopener noreferrer">Open in GitLab</a>
            @endif
            @can('edit planning tasks')
                <a class="button secondary" href="{{ route('planning.edit', $task) }}" aria-label="Edit {{ $task->title }}">Edit</a>
            @endcan
            @can('delete planning tasks')
                <button
                    class="button secondary text-red-700! hover:bg-red-50!"
                    type="button"
                    aria-label="Delete {{ $task->title }}"
                    aria-haspopup="dialog"
                    data-open-modal="delete-task-{{ $task->id }}"
                >Delete</button>
                <x-modal
                    :id="'delete-task-' . $task->id"
                    title="Delete task?"
                    :description="'“' . $task->title . '” and its assignments will be permanently deleted.'"
                >
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <form method="dialog">
                            <button class="button secondary" type="submit" autofocus>Cancel</button>
                        </form>
                        <form method="post" action="{{ route('planning.destroy', $task) }}">
                            @csrf
                            @method('DELETE')
                            <button class="button bg-red-700! hover:bg-red-800!" type="submit">Delete task</button>
                        </form>
                    </div>
                </x-modal>
            @endcan
        </div>
    @endif
</article>
