<article class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-3 border-t border-zinc-200 pt-4 [overflow-wrap:anywhere]">
    <div class="min-w-0 flex-1 basis-56">
        <h3 class="text-sm font-semibold">{{ $task->title }}</h3>
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
        @if ($task->gitlab_url)
            <a class="button secondary mt-3" href="{{ $task->gitlab_url }}" target="_blank" rel="noopener noreferrer">Open in GitLab</a>
        @endif
    </div>

    @canany(['edit planning tasks', 'delete planning tasks'])
        <div class="grid justify-items-start gap-2 sm:justify-items-end">
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
            <div class="flex gap-2">
                @can('edit planning tasks')
                    <a class="button secondary size-9 items-center justify-center p-0!" href="{{ route('planning.edit', $task) }}" aria-label="Edit {{ $task->title }}" title="Edit task">
                        <i class="fi fi-rr-pencil leading-none" aria-hidden="true"></i>
                    </a>
                @endcan
                @can('delete planning tasks')
                    <button
                        class="button secondary size-9 items-center justify-center p-0! text-red-700! hover:bg-red-50!"
                        type="button"
                        aria-label="Delete {{ $task->title }}"
                        aria-haspopup="dialog"
                        title="Delete task"
                        data-open-modal="delete-task-{{ $task->id }}"
                    >
                        <i class="fi fi-rr-trash leading-none" aria-hidden="true"></i>
                    </button>
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
        </div>
    @endcanany
</article>
