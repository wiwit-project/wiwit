<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
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

        $income = $this->monthlyTotals(TransactionType::Income, $start, $end);
        $expense = $this->monthlyTotals(TransactionType::Expense, $start, $end);

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
    private function monthlyTotals(TransactionType $type, CarbonInterface $start, CarbonInterface $end): Collection
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
                    'display' => false,
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
