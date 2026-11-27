@props(['id', 'title', 'description' => null])

<dialog
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    @if ($description) aria-describedby="{{ $id }}-description" @endif
    {{ $attributes->class(['card m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto text-zinc-900 backdrop:bg-zinc-950/50']) }}
>
    <h2 id="{{ $id }}-title" class="section-title">{{ $title }}</h2>

    @if ($description)
        <p id="{{ $id }}-description" class="muted mt-2 text-sm [overflow-wrap:anywhere]">{{ $description }}</p>
    @endif

    {{ $slot }}
</dialog>
