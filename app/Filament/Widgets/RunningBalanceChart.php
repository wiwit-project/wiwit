<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithTransactions;
use Filament\Widgets\ChartWidget;

class RunningBalanceChart extends ChartWidget
{
    use InteractsWithTransactions;

    protected ?string $heading = 'Running balance';

    protected ?string $description = 'Cumulative net since your first transaction';

    protected static ?int $sort = 11;

    protected ?string $maxHeight = '18rem';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    private const MONTHS = 12;

    protected function getData(): array
    {
        $end = today()->endOfMonth();
        $start = today()->startOfMonth()->subMonths(self::MONTHS - 1);

        $running = $this->netBefore($start);

        $balances = $this
            ->bucketByMonth($this->dailyTotals(null, $start, $end), $start, $end)
            ->map(function (float $net) use (&$running): float {
                $running += $net;

                return $running;
            });

        return [
            'datasets' => [
                [
                    'label' => 'Balance',
                    'data' => $balances->values()->all(),
                    'borderColor' => '#4f46e5',
                    'backgroundColor' => 'rgba(79, 70, 229, 0.12)',
                    'borderWidth' => 2,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'fill' => 'origin',
                ],
            ],
            'labels' => $balances->keys()
                ->map(fn (string $month): string => date('M', strtotime($month.'-01')))
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
