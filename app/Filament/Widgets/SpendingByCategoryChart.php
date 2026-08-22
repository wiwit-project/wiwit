<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\InteractsWithTransactions;
use Filament\Widgets\ChartWidget;

class SpendingByCategoryChart extends ChartWidget
{
    use InteractsWithTransactions;

    protected ?string $heading = 'Spending by category';

    protected ?string $description = 'This month';

    protected static ?int $sort = 20;

    protected ?string $maxHeight = '18rem';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    /**
     * Categories beyond this many are folded into a single "Others" slice.
     */
    private const VISIBLE_CATEGORIES = 6;

    protected function getData(): array
    {
        $totals = $this->categoryTotals(TransactionType::Expense, today()->startOfMonth(), today()->endOfMonth());

        if ($totals->count() > self::VISIBLE_CATEGORIES) {
            $others = (float) $totals->slice(self::VISIBLE_CATEGORIES)->sum();
            $totals = $totals->take(self::VISIBLE_CATEGORIES)->put('Others', $others);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Spending',
                    'data' => $totals->values()->all(),
                    'backgroundColor' => array_slice(self::PALETTE, 0, $totals->count()),
                    'borderWidth' => 0,
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $totals->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '68%',
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
        ];
    }
}
