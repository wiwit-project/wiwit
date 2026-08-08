@use('App\Support\AppVersion')

@php
    $version = AppVersion::fromConfig();
    $label = $version->displayString();
    $url = $version->url();
@endphp

@if (filled($label))
    <div
        style="width: 100%; padding: 1rem 1.5rem; color: var(--gray-400); font-size: 12px; line-height: 20px; text-align: center;">
        @if (filled($url))
            <a href="{{ $url }}" style="color: inherit; text-decoration: none;">
                {{ $label }}
            </a>
        @else
            {{ $label }}
        @endif
    </div>
@endif
