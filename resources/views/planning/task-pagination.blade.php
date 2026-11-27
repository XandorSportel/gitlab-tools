@if ($tasks->hasPages())
    <nav class="mt-5 flex flex-wrap gap-2" aria-label="{{ $label }}">
        @if ($tasks->previousPageUrl())
            <a class="button secondary" href="{{ $tasks->previousPageUrl() }}">Previous</a>
        @endif
        @if ($tasks->nextPageUrl())
            <a class="button secondary" href="{{ $tasks->nextPageUrl() }}">Next</a>
        @endif
    </nav>
@endif
