<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithTransactions;
use Carbon\CarbonInterface;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class IncomeVsSpendingChart extends ChartWidget
{
    use InteractsWithTransactions;

    protected ?string $heading = 'Income vs spending';

    protected ?string $description = 'Last 12 months';

    protected static ?int $sort = 10;

    protected ?string $maxHeight = '18rem';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 8];

    private const MONTHS = 12;

    protected function getData(): array
    {
        $end = today()->endOfMonth();
        $start = today()->startOfMonth()->subMonths(self::MONTHS - 1);

        $income = $this->monthlyTotals('income', $start, $end);
        $expense = $this->monthlyTotals('expense', $start, $end);

        return [
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => $income->values()->all(),
                    'backgroundColor' => self::INCOME_COLOR,
                    'borderRadius' => 3,
                ],
                [
                    'label' => 'Spending',
                    'data' => $expense->values()->all(),
                    'backgroundColor' => self::EXPENSE_COLOR,
                    'borderRadius' => 3,
                ],
                [
                    'type' => 'line',
                    'label' => 'Net',
                    'data' => $income->map(
                        fn (float $total, string $month): float => $total - $expense->get($month, 0.0),
                    )->values()->all(),
                    'borderColor' => '#64748b',
                    'backgroundColor' => '#64748b',
                    'borderWidth' => 2,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                    'fill' => false,
                ],
            ],
            // A 12 month window visits each month exactly once, so the short name is unambiguous.
            'labels' => $income->keys()
                ->map(fn (string $month): string => date('M', strtotime($month.'-01')))
                ->all(),
        ];
    }

    /**
     * @return Collection<string, float>
     */
    private function monthlyTotals(string $type, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->bucketByMonth($this->dailyTotals($type, $start, $end), $start, $end);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                    'align' => 'start',
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
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
