@php
    $title = old('title', $task?->title);
    $description = old('description', $task?->description);
    $assigneeIds = old('assignee_ids', session()->hasOldInput() ? [] : $selectedAssigneeIds);
@endphp

@if ($errors->any())
    <div class="alert mt-4" role="alert">
        <ul class="list-disc pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form class="mt-4 grid gap-4" method="post" action="{{ $action }}">
    @csrf
    @if ($task)
        @method('PATCH')
    @endif

    <div>
        <label for="title">Title</label>
        <input id="title" name="title" value="{{ is_string($title) ? $title : '' }}" maxlength="255" required>
    </div>

    <div>
        <label for="description">Description</label>
        <textarea class="w-full rounded-[7px] border border-zinc-300 bg-white px-[11px] py-2.5 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100" id="description" name="description" rows="3" maxlength="5000">{{ is_string($description) ? $description : '' }}</textarea>
    </div>

    <fieldset class="min-w-0">
        <legend class="mb-2 text-[13px] font-semibold">Assignees</legend>
        <div class="max-h-44 overflow-y-auto rounded-[7px] border border-zinc-300 p-2">
            @foreach ($assignableUsers as $assignee)
                <label class="mb-0 flex cursor-pointer items-start gap-2 rounded p-2 hover:bg-zinc-50 [overflow-wrap:anywhere]">
                    <input class="mt-0.5 w-auto! shrink-0 accent-orange-600 focus-visible:outline-2!" type="checkbox" name="assignee_ids[]" value="{{ $assignee->id }}" @checked(in_array($assignee->id, (array) $assigneeIds))>
                    <span class="min-w-0">{{ $assignee->name }} <small class="muted block font-normal">{{ $assignee->email }}</small></span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="flex flex-wrap gap-2">
        <button class="primary" type="submit">{{ $task ? 'Save changes' : 'Add task' }}</button>
        <a class="button secondary" href="{{ route($cancelRoute) }}">Cancel</a>
    </div>
</form>
