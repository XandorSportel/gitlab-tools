@props(['id', 'action', 'title', 'description'])

<button class="button secondary text-red-700!" type="button" aria-haspopup="dialog" data-open-modal="{{ $id }}">Delete</button>

<x-modal :id="$id" :title="$title" :description="$description">
    <div class="mt-5 flex flex-wrap justify-end gap-2">
        <form method="dialog">
            <button class="button secondary" type="submit" autofocus>Cancel</button>
        </form>
        <form method="post" action="{{ $action }}">
            @csrf
            @method('DELETE')
            <button class="button bg-red-700! hover:bg-red-800!" type="submit">Confirm deletion</button>
        </form>
    </div>
</x-modal>
