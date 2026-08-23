<?php

namespace App\Support\Analytics;

use App\Enums\Interval;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Aggregate transaction queries for analytics API
 */
class TransactionAnalytics
{
    /**
     * Label for transactions without a category, matching the dashboard widgets.
     */
    public const UNCATEGORIZED = 'Uncategorized';

    /**
     * Income, expense and count for one period.
     *
     * @return array{income: float, expense: float, net: float, transaction_count: int}
     */
    public function totals(User $user, Period $period): array
    {
        $transactions = $this->scoped($user, $period);
        $income = (float) $transactions->clone()->incomes()->sum('transactions.amount');
        $expense = (float) $transactions->clone()->expenses()->sum('transactions.amount');
        $transactionCount = $transactions->count();

        $income = self::money($income);
        $expense = self::money($expense);
        $net = self::money($income - $expense);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
            'transaction_count' => $transactionCount,
        ];
    }

    /**
     * Net of every transaction dated strictly before the given day.
     */
    public function netBefore(User $user, CarbonInterface $date): float
    {
        $query = Transaction::query()
            ->where('transactions.user_id', $user->getKey())
            ->where('transactions.transaction_date', '<', $date->copy()->startOfDay());

        $income = $query->clone()->incomes()->sum('transactions.amount');
        $expense = $query->clone()->expenses()->sum('transactions.amount');

        return self::money((float) $income - (float) $expense);
    }

    /**
     * Per-day income and expense totals
     *
     * @return Collection<string, array{income: float, expense: float}>
     */
    public function dailyTotals(User $user, Period $period): Collection
    {
        $income = $this->dailyTotalsForType($user, $period, TransactionType::Income);
        $expense = $this->dailyTotalsForType($user, $period, TransactionType::Expense);

        return $income->keys()
            ->merge($expense->keys())
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $date): array => [
                $date => [
                    'income' => (float) $income->get($date, 0),
                    'expense' => (float) $expense->get($date, 0),
                ],
            ]);
    }

    /**
     * Get daily totals for one transaction type without conditional SQL.
     *
     * @return Collection<string, float>
     */
    private function dailyTotalsForType(User $user, Period $period, TransactionType $type): Collection
    {
        return $this->scoped($user, $period)
            ->ofType($type)
            ->select('transactions.transaction_date')
            ->selectRaw('sum(transactions.amount) as total')
            ->groupBy('transactions.transaction_date')
            ->pluck('total', 'transactions.transaction_date')
            ->mapWithKeys(fn ($total, $date): array => [
                substr((string) $date, 0, 10) => (float) $total,
            ]);
    }

    /**
     * Turn the sparse daily rows from SQL into the dense series a chart needs.
     *
     * SQL omits days without transactions, so initialize every bucket at zero to
     * keep gaps visible in the chart.
     *
     * @param  Collection<string, array{income: float, expense: float}>  $daily
     * @return Collection<int, array{period: string, period_start: string, period_end: string, income: float, expense: float, net: float}>
     */
    public function bucketBy(Collection $daily, Period $period, Interval $interval): Collection
    {
        $buckets = collect();

        for (
            $cursor = $interval->startOf($period->from);
            $cursor->lessThanOrEqualTo($period->to);
            $cursor = $interval->next($cursor)
        ) {
            $start = $cursor->lessThan($period->from) ? $period->from : $cursor;
            $end = $interval->endOf($cursor)->greaterThan($period->to) ? $period->to : $interval->endOf($cursor);

            $buckets->put($interval->format($cursor), [
                'period' => $interval->format($cursor),
                'period_start' => $start->format(Period::DATE_FORMAT),
                'period_end' => $end->format(Period::DATE_FORMAT),
                'income' => 0.0,
                'expense' => 0.0,
                'net' => 0.0,
            ]);
        }

        foreach ($daily as $date => $totals) {
            $key = $interval->format(CarbonImmutable::createFromFormat(Period::DATE_FORMAT, $date)->startOfDay());

            if (! $buckets->has($key)) {
                continue;
            }

            $bucket = $buckets->get($key);
            $bucket['income'] = self::money($bucket['income'] + $totals['income']);
            $bucket['expense'] = self::money($bucket['expense'] + $totals['expense']);
            $bucket['net'] = self::money($bucket['income'] - $bucket['expense']);

            $buckets->put($key, $bucket);
        }

        return $buckets->values();
    }

    /**
     * Category breakdown for one period, sorted by total descending.
     *
     * @return array{total: float, category_count: int, data: array<int, array{category_id: ?int, category_name: string, total: float, share: float}>}
     */
    public function categoryTotals(User $user, Period $period, TransactionType $type, ?int $limit = null): array
    {
        $rows = $this->scoped($user, $period)
            ->ofType($type)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->select('transactions.category_id', 'categories.name as category_name')
            ->selectRaw('sum(transactions.amount) as total')
            ->groupBy('transactions.category_id', 'categories.name')
            ->get()
            ->map(fn (Transaction $row): array => [
                'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                'category_name' => $row->category_name ?? self::UNCATEGORIZED,
                'total' => (float) $row->total,
            ])
            ->sortBy([['total', 'desc'], ['category_name', 'asc']])
            ->values();

        $total = (float) $rows->sum('total');

        $data = ($limit === null ? $rows : $rows->take($limit))
            ->map(fn (array $row): array => [
                'category_id' => $row['category_id'],
                'category_name' => $row['category_name'],
                'total' => self::money($row['total']),
                'share' => $total > 0.0 ? round($row['total'] / $total, 4) : 0.0,
            ])
            ->values()
            ->all();

        return [
            'total' => self::money($total),
            'category_count' => $rows->count(),
            'data' => $data,
        ];
    }

    /**
     * Get user transactions queryable.
     */
    private function scoped(User $user, Period $period)
    {
        $query = Transaction::query();

        return $query
            ->where('transactions.user_id', $user->getKey())
            ->whereBetween('transactions.transaction_date', [
                $period->from->startOfDay(),
                $period->to->endOfDay(),
            ]);
    }

    public static function money(float $value): float
    {
        return round($value, 2);
    }
}
