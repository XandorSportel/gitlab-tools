@extends('admin.layout')

@section('admin-content')
    <section class="card" aria-labelledby="tags-title">
        <h2 class="section-title" id="tags-title">Planning tags</h2>
        <form class="my-4 flex flex-wrap items-end gap-2" method="post" action="{{ route('admin.tags.store') }}">
            @csrf
            <div class="min-w-0 flex-1 basis-56">
                <label for="tag-name">New tag</label>
                <input id="tag-name" name="name" maxlength="50" required>
            </div>
            <button class="primary" type="submit">Add tag</button>
        </form>
        <ul class="divide-y divide-zinc-200">
            @forelse ($tags as $tag)
                <li class="flex flex-wrap items-center gap-3 py-3">
                    <form class="flex min-w-0 flex-1 basis-64 flex-wrap items-center gap-2" method="post" action="{{ route('admin.tags.update', $tag) }}">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="tag-{{ $tag->id }}">Rename {{ $tag->name }}</label>
                        <input class="min-w-0 flex-1 basis-40" id="tag-{{ $tag->id }}" name="name" value="{{ $tag->name }}" maxlength="50" required>
                        <span class="muted text-sm">{{ $tag->issues_count }} {{ Str::plural('issue', $tag->issues_count) }}</span>
                        <button class="button secondary" type="submit">Rename</button>
                    </form>
                    <x-admin-delete :id="'delete-tag-'.$tag->id" :action="route('admin.tags.destroy', $tag)" title="Delete Planning tag?" :description="'The tag '.$tag->name.' will be removed from all issues.'" />
                </li>
            @empty
                <li class="muted py-3">No Planning tags yet.</li>
            @endforelse
        </ul>
        {{ $tags->links() }}
    </section>
@endsection
