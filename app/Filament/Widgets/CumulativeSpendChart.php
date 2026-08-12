<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithTransactions;
use Carbon\CarbonInterface;
use Filament\Widgets\ChartWidget;

class CumulativeSpendChart extends ChartWidget
{
    use InteractsWithTransactions;

    protected ?string $heading = 'Cumulative spend';

    protected ?string $description = 'This month vs last';

    protected static ?int $sort = 22;

    protected ?string $maxHeight = '18rem';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    protected function getData(): array
    {
        $today = today();
        $lastMonth = $today->copy()->subMonthNoOverflow();

        $days = max($today->daysInMonth, $lastMonth->daysInMonth);

        return [
            'datasets' => [
                [
                    'label' => 'This month',
                    // Stop at today so the line didn't have empty points for upcoming days
                    'data' => $this->cumulativeSpend($today, $days, upTo: $today->day),
                    'borderColor' => self::EXPENSE_COLOR,
                    'backgroundColor' => 'rgba(225, 29, 72, 0.12)',
                    'borderWidth' => 2,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'fill' => 'origin',
                    'spanGaps' => false,
                ],
                [
                    'label' => 'Last month',
                    'data' => $this->cumulativeSpend($lastMonth, $days, upTo: $lastMonth->daysInMonth),
                    'borderColor' => '#94a3b8',
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 1.5,
                    'borderDash' => [4, 4],
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'fill' => false,
                ],
            ],
            'labels' => array_map('strval', range(1, $days)),
        ];
    }

    private function cumulativeSpend(CarbonInterface $month, int $days, int $upTo): array
    {
        $start = $month->copy()->startOfMonth();
        $totals = $this->dailyTotals('expense', $start, $month->copy()->endOfMonth());

        $running = 0.0;
        $series = [];

        foreach (range(1, $days) as $day) {
            if ($day > $upTo) {
                $series[] = null;

                continue;
            }

            $running += $totals->get($start->copy()->addDays($day - 1)->format('Y-m-d'), 0.0);
            $series[] = $running;
        }

        return $series;
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
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['maxTicksLimit' => 8],
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
