<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithTransactions;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class SpendingStatsOverview extends BaseWidget
{
    use InteractsWithTransactions;

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $yearStart = $today->copy()->startOfYear();
        $yearEnd = $today->copy()->endOfYear();

        $todayNet = $this->netFor(
            fn (Builder $query): Builder => $query->whereDate('transaction_date', $today),
        );
        $monthNet = $this->netFor(
            fn (Builder $query): Builder => $query->whereBetween('transaction_date', [$monthStart, $monthEnd]),
        );
        $yearNet = $this->netFor(
            fn (Builder $query): Builder => $query->whereBetween('transaction_date', [$yearStart, $yearEnd]),
        );

        return [
            Stat::make('Today\'s Net Cash Flow', $this->formatMoney($todayNet))
                ->description('Average daily spending this month: '.$this->formatUnsignedMoney(
                    $this->transactionsQuery()
                        ->expenses()
                        ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                        ->sum('amount') / $today->day,
                ))
                ->chart($this->dailyNetSeries(14))
                ->chartColor($this->colorFor($todayNet)),
            Stat::make('Monthly Net Cash Flow', $this->formatMoney($monthNet))
                ->description('Net per month over the last 12 months')
                ->chart($this->monthlyNetSeries(12))
                ->chartColor($this->colorFor($monthNet)),
            Stat::make('Yearly Net Cash Flow', $this->formatMoney($yearNet))
                ->description('Running net so far this year')
                ->chart($this->cumulativeYearNetSeries())
                ->chartColor($this->colorFor($yearNet)),
        ];
    }

    private function dailyNetSeries(int $days): array
    {
        $end = today();
        $start = $end->copy()->subDays($days - 1);

        $totals = $this->dailyTotals(null, $start->copy()->startOfDay(), $end->copy()->endOfDay());

        return collect(range(0, $days - 1))
            ->map(fn (int $offset): float => $totals->get($start->copy()->addDays($offset)->format('Y-m-d'), 0.0))
            ->all();
    }

    private function monthlyNetSeries(int $months): array
    {
        $end = today();
        $start = $end->copy()->startOfMonth()->subMonths($months - 1);

        return $this
            ->bucketByMonth(
                $this->dailyTotals(null, $start, $end->copy()->endOfDay()),
                $start,
                $end,
            )
            ->values()
            ->all();
    }

    /**
     * Running net from January through the current month.
     *
     * @return array<int, float>
     */
    private function cumulativeYearNetSeries(): array
    {
        $end = today();
        $start = $end->copy()->startOfYear();

        $running = 0.0;

        return $this
            ->bucketByMonth(
                $this->dailyTotals(null, $start, $end->copy()->endOfDay()),
                $start,
                $end,
            )
            ->map(function (float $net) use (&$running): float {
                $running += $net;

                return $running;
            })
            ->values()
            ->all();
    }

    private function netFor(callable $scope): float
    {
        $query = $scope($this->transactionsQuery());

        return (float) $query->clone()->incomes()->sum('amount')
            - (float) $query->clone()->expenses()->sum('amount');
    }

    private function colorFor(float $amount): string
    {
        return $amount >= 0 ? 'success' : 'danger';
    }

    private function formatMoney(float $amount): string
    {
        return ($amount >= 0 ? '+' : '-').$this->formatUnsignedMoney(abs($amount));
    }

    private function formatUnsignedMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
