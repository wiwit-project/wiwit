@php
    $rows = $this->getGrid();
    $weeks = count($rows[0]['cells'] ?? []);

    $shade = fn(int $level): string => match ($level) {
        1 => 'bg-rose-100 dark:bg-rose-950',
        2 => 'bg-rose-200 dark:bg-rose-900',
        3 => 'bg-rose-400 dark:bg-rose-700',
        4 => 'bg-rose-600 dark:bg-rose-500',
        default => 'bg-gray-100 dark:bg-white/5',
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="When you spend" description="This month">
        <div class="w-full max-w-4xl space-y-0.5 sm:space-y-1">
            <div class="grid gap-0.5 text-[0.6875rem] text-gray-500 sm:gap-1 sm:text-xs dark:text-gray-400"
                style="grid-template-columns: 2rem repeat({{ $weeks }}, minmax(0, 1fr));">
                <div></div>

                @for ($week = 1; $week <= $weeks; $week++)
                    <div class="text-center">
                        <span class="sm:hidden">W{{ $week }}</span>
                        <span class="hidden sm:inline">Week {{ $week }}</span>
                    </div>
                @endfor
            </div>

            @foreach ($rows as $row)
                <div class="grid items-center gap-0.5 sm:gap-1"
                    style="grid-template-columns: 2rem repeat({{ $weeks }}, minmax(0, 1fr));">
                    <div class="text-[0.6875rem] text-gray-500 sm:text-xs dark:text-gray-400">
                        {{ $row['label'] }}
                    </div>

                    @foreach ($row['cells'] as $cell)
                        @if ($cell === null)
                            <div class="h-6 rounded-sm bg-transparent sm:h-7 sm:rounded-md"></div>
                        @else
                            <div class="h-6 rounded-sm sm:h-7 sm:rounded-md {{ $shade($cell['level']) }}"
                                title="{{ $row['label'] }} {{ $cell['day'] }} - {{ number_format($cell['total'], 2, '.', '') }}">
                            </div>
                        @endif
                    @endforeach
                </div>
            @endforeach

            <div class="flex items-center justify-end gap-2 pt-2 text-xs text-gray-500 dark:text-gray-400">
                <span>Less</span>
                @foreach ([0, 1, 2, 3, 4] as $level)
                    <span class="h-3 w-3 rounded-sm {{ $shade($level) }}"></span>
                @endforeach
                <span>More</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
