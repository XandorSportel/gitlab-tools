<div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2">
    @if ($paginator->hasPages())
        <nav class="flex flex-wrap items-center gap-2" aria-label="Issue pages">
            @if ($paginator->onFirstPage())
                <span class="button secondary opacity-60" aria-disabled="true">Previous</span>
            @else
                <a class="button secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="muted px-1">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="button" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="button secondary" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="button secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="button secondary opacity-60" aria-disabled="true">Next</span>
            @endif
        </nav>
    @endif

    <p class="muted text-sm">Showing {{ $paginator->count() }} of {{ $paginator->total() }}</p>
</div>
