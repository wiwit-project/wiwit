<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\InteractsWithTransactions;
use Filament\Widgets\ChartWidget;

class TopCategoriesComparisonChart extends ChartWidget
{
    use InteractsWithTransactions;

    protected ?string $heading = 'Top categories';

    protected static ?int $sort = 21;

    protected ?string $maxHeight = '18rem';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 4,
    ];

    private const VISIBLE_CATEGORIES = 6;

    protected function getData(): array
    {
        $thisMonth = $this
            ->categoryTotals(TransactionType::Expense, today()->startOfMonth(), today()->endOfMonth())
            ->take(self::VISIBLE_CATEGORIES);

        $lastMonth = $this->categoryTotals(
            TransactionType::Expense,
            today()->subMonthNoOverflow()->startOfMonth(),
            today()->subMonthNoOverflow()->endOfMonth(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'This month',
                    'data' => $thisMonth->values()->all(),
                    'backgroundColor' => self::EXPENSE_COLOR,
                    'borderRadius' => 3,
                ],
                [
                    'label' => 'Last month',
                    'data' => $thisMonth->keys()
                        ->map(fn (string $category): float => $lastMonth->get($category, 0.0))
                        ->all(),
                    'backgroundColor' => '#cbd5e1',
                    'borderRadius' => 3,
                ],
            ],
            'labels' => $thisMonth->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
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
                    'beginAtZero' => true,
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
