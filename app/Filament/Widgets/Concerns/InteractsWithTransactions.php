<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait InteractsWithTransactions
{
    /**
     * Shared palletes by every dashboard chart
     */
    protected const PALETTE = [
        '#0d9488',
        '#4f46e5',
        '#e11d48',
        '#f59e0b',
        '#7c3aed',
        '#0ea5e9',
        '#cbd5e1',
    ];

    protected const INCOME_COLOR = '#0d9488';

    protected const EXPENSE_COLOR = '#e11d48';

    protected function transactionsQuery(): Builder
    {
        return Transaction::query()->where('transactions.user_id', auth()->id());
    }

    protected function dailyTotals(?string $type, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $query = $this->transactionsQuery()
            ->whereBetween('transaction_date', [$from, $to]);

        if ($type === null) {
            $query->selectRaw("transaction_date, sum(case when type = 'income' then amount else -amount end) as total");
        } else {
            $query->where('type', $type)->selectRaw('transaction_date, sum(amount) as total');
        }

        return $query
            ->groupBy('transaction_date')
            ->pluck('total', 'transaction_date')
            ->mapWithKeys(fn ($total, $date): array => [
                substr((string) $date, 0, 10) => (float) $total,
            ]);
    }

    /**
     * Totals per category name
     *
     * @return Collection<string, float>
     */
    protected function categoryTotals(string $type, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->transactionsQuery()
            ->where('transactions.type', $type)
            ->whereBetween('transactions.transaction_date', [$from, $to])
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw("coalesce(categories.name, 'Uncategorized') as category_name, sum(transactions.amount) as total")
            ->groupBy('category_name')
            ->pluck('total', 'category_name')
            ->map(fn ($total): float => (float) $total)
            ->sortDesc();
    }

    /**
     * Net of every transaction dated strictly before the given day.
     */
    protected function netBefore(CarbonInterface $date): float
    {
        return (float) $this->transactionsQuery()
            ->whereDate('transaction_date', '<', $date)
            ->selectRaw("coalesce(sum(case when type = 'income' then amount else -amount end), 0) as total")
            ->value('total');
    }

    protected function bucketByMonth(Collection $dailyTotals, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $months = collect();

        for (
            $month = $from->copy()->startOfMonth();
            $month->lessThanOrEqualTo($to);
            $month = $month->copy()->addMonth()
        ) {
            $months->put($month->format('Y-m'), 0.0);
        }

        foreach ($dailyTotals as $date => $total) {
            $key = substr($date, 0, 7);

            if ($months->has($key)) {
                $months->put($key, $months->get($key) + $total);
            }
        }

        return $months;
    }
}
