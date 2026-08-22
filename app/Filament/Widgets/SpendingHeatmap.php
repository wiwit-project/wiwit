<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\InteractsWithTransactions;
use Filament\Widgets\Widget;

class SpendingHeatmap extends Widget
{
    use InteractsWithTransactions;

    protected string $view = 'filament.widgets.spending-heatmap';

    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    public function getGrid(): array
    {
        $monthStart = today()->startOfMonth();
        $daysInMonth = $monthStart->daysInMonth;
        $totals = $this->dailyTotals(TransactionType::Expense, $monthStart, today()->endOfMonth());
        $max = (float) $totals->max();

        // Offset of the 1st within its week to make the grid lines up like a calendar.
        $offset = $monthStart->dayOfWeekIso - 1;
        $weeks = (int) ceil(($offset + $daysInMonth) / 7);

        $rows = [];

        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday => $label) {
            $cells = [];

            foreach (range(0, $weeks - 1) as $week) {
                $day = ($week * 7) + $weekday - $offset + 1;

                if ($day < 1 || $day > $daysInMonth) {
                    $cells[] = null;

                    continue;
                }

                $total = $totals->get($monthStart->copy()->addDays($day - 1)->format('Y-m-d'), 0.0);

                $cells[] = [
                    'day' => $day,
                    'total' => $total,
                    'level' => $this->levelFor($total, $max),
                ];
            }

            $rows[] = ['label' => $label, 'cells' => $cells];
        }

        return $rows;
    }

    /**
     * Bucket a day's spending into five levels
     */
    private function levelFor(float $total, float $max): int
    {
        if ($total <= 0.0 || $max <= 0.0) {
            return 0;
        }

        return (int) min(4, max(1, ceil(($total / $max) * 4)));
    }
}
