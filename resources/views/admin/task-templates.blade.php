@extends('admin.layout')

@section('admin-content')
    <section class="card" aria-labelledby="standard-tasks-title">
        <h2 class="section-title" id="standard-tasks-title">Standard tasks</h2>
        <form class="my-4 flex flex-wrap items-end gap-2" method="post" action="{{ route('admin.task-templates.store') }}">
            @csrf
            <div class="min-w-0 flex-1 basis-56">
                <label for="new-template-title">New standard task</label>
                <input id="new-template-title" name="title" maxlength="255" required>
            </div>
            <button class="primary" type="submit">Add task</button>
        </form>
        <ul class="divide-y divide-zinc-200">
            @forelse ($templates as $template)
                <li class="flex flex-wrap items-center gap-3 py-3">
                    <form class="flex min-w-0 flex-1 basis-64 flex-wrap items-center gap-2" method="post" action="{{ route('admin.task-templates.update', $template) }}">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="template-{{ $template->id }}">Title of {{ $template->title }}</label>
                        <input class="min-w-0 flex-1 basis-40" id="template-{{ $template->id }}" name="title" value="{{ $template->title }}" maxlength="255" required>
                        <button class="button secondary" type="submit">Save</button>
                    </form>
                    <x-admin-delete
                        :id="'delete-template-'.$template->id"
                        :action="route('admin.task-templates.destroy', $template)"
                        title="Delete standard task?"
                        :description="'“'.$template->title.'” will be removed from the standard task list.'"
                    />
                </li>
            @empty
                <li class="muted py-3">No standard tasks yet.</li>
            @endforelse
        </ul>
        {{ $templates->links('components.pagination') }}
    </section>
@endsection
