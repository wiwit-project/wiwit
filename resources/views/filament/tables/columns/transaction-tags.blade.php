<div class="flex flex-wrap gap-1.5">
    @foreach ($getRecord()->tags as $tag)
        @php($badgeAttributes = \App\Support\TagBadge::attributes($tag->color))

        <span class="{{ $badgeAttributes['class'] }}" style="{{ $badgeAttributes['style'] }}">
            <x-filament::badge color="gray" size="sm">
                {{ $tag->name }}
            </x-filament::badge>
        </span>
    @endforeach
</div>
