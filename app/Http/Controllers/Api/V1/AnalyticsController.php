<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Interval;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Analytics\Period;
use App\Support\Analytics\TransactionAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @group Analytics
 *
 * Raw spending aggregates over a supplied date range
 *
 * @authenticated
 */
class AnalyticsController extends Controller
{
    /**
     * Months of history the overview endpoint bundles as a monthly series.
     */
    private const OVERVIEW_TRAILING_MONTHS = 12;

    public function __construct(private readonly TransactionAnalytics $analytics) {}

    /**
     * Sum transactions by time series
     *
     * History of income/expenses in a time series for a given period of time.
     *
     * @queryParam date_from string required Start of the period, inclusive, in YYYY-MM-DD format. Example: 2026-01-01
     * @queryParam date_to string required End of the period, inclusive, in YYYY-MM-DD format. Must not be before date_from. The period must not exceed 366 days. Example: 2026-08-31
     * @queryParam interval string Granularity. Defaults to month. Enum: day, week, month Example: month
     *
     * @responseField period.from Start of the period, echoed back.
     * @responseField period.to End of the period, echoed back.
     * @responseField interval Granularity used.
     * @responseField meta.opening_balance Net of every transaction dated strictly before date_from.
     * @responseField data[].period Bucket key: YYYY-MM-DD for day, YYYY-Wnn for week, YYYY-MM for month.
     * @responseField data[].period_start First day the bucket covers, clamped to the period.
     * @responseField data[].period_end Last day the bucket covers, clamped to the period.
     * @responseField data[].income Sum of income amounts in the bucket.
     * @responseField data[].expense Sum of expense amounts in the bucket.
     * @responseField data[].net Bucket income minus bucket expense.
     * @responseField links.self Link back to this series.
     */
    public function series(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            ...$this->rangeRules(),
            'interval' => ['sometimes', Rule::enum(Interval::class)],
        ])->validate();

        $period = $this->rangeFrom($validated);
        $interval = isset($validated['interval']) ? Interval::from($validated['interval']) : Interval::Month;

        return $this->respond([
            ...$this->seriesPayload($request->user(), $period, $interval),
            'links' => [
                'self' => $this->link('api.v1.analytics.series', [...$period->toQuery(), 'interval' => $interval->value]),
            ],
        ]);
    }

    /**
     * Sum transactions by categories
     *
     * Totals spending/income per category for a given period
     *
     * @queryParam date_from string required Start of the period, inclusive, in YYYY-MM-DD format. Example: 2026-08-01
     * @queryParam date_to string required End of the period, inclusive, in YYYY-MM-DD format. Must not be before date_from. The period must not exceed 366 days. Example: 2026-08-31
     * @queryParam type string required Which side to break down. Enum: income, expense Example: expense
     * @queryParam limit integer Return only the top N categories, from 1 to 100. Example: 5
     *
     * @responseField period.from Start of the period, echoed back.
     * @responseField period.to End of the period, echoed back.
     * @responseField type Which side was broken down.
     * @responseField meta.total Total across every category, ignoring limit.
     * @responseField meta.category_count Number of categories with activity, ignoring limit.
     * @responseField data[].category_id ID of the category, or null for the uncategorized row.
     * @responseField data[].category_name Name of the category, or "Uncategorized".
     * @responseField data[].total Sum of amounts for this category.
     * @responseField data[].share This category's fraction of meta.total, from 0 to 1.
     * @responseField links.self Link back to this breakdown.
     */
    public function categories(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            ...$this->rangeRules(),
            'type' => ['required', Rule::enum(TransactionType::class)],
            'limit' => ['sometimes', 'integer', 'between:1,100'],
        ])->validate();

        $period = $this->rangeFrom($validated);
        $type = TransactionType::from($validated['type']);
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : null;

        return $this->respond([
            ...$this->categoriesPayload($request->user(), $period, $type, $limit),
            'links' => [
                'self' => $this->link('api.v1.analytics.categories', array_filter([
                    ...$period->toQuery(),
                    'type' => $type->value,
                    'limit' => $limit,
                ], fn ($value): bool => $value !== null)),
            ],
        ]);
    }

    /**
     * Get analytics overview
     *
     * Return all aggregated analytics data
     *
     * @queryParam month string The month, in YYYY-MM format. Defaults to the current month. Example: 2026-08
     *
     * @responseField period.from First day of the reported month.
     * @responseField period.to Last day of the reported month.
     * @responseField month The reported month, in YYYY-MM format.
     * @responseField summary Totals for the reported month.
     * @responseField series Trailing twelve months at interval=month, as in GET /analytics/series.
     * @responseField categories Expense breakdown for the reported month, as in GET /analytics/categories.
     * @responseField links.self Link back to this overview.
     * @responseField links.series Link to the trailing twelve-month series.
     * @responseField links.categories Link to the expense categories for the reported month.
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'month' => ['sometimes', 'date_format:'.Period::MONTH_FORMAT],
        ])->validate();

        $month = $validated['month'] ?? CarbonImmutable::now()->format(Period::MONTH_FORMAT);
        $period = Period::forMonth($month);
        $trailing = Period::trailingMonths($period->to, self::OVERVIEW_TRAILING_MONTHS);
        $user = $request->user();
        $type = TransactionType::Expense;

        return $this->respond([
            'period' => $period->toArray(),
            'month' => $month,
            'summary' => $this->summaryPayload($user, $period),
            'series' => $this->seriesPayload($user, $trailing, Interval::Month),
            'categories' => $this->categoriesPayload($user, $period, $type),
            'links' => [
                'self' => $this->link('api.v1.analytics.overview', ['month' => $month]),
                'series' => $this->link('api.v1.analytics.series', [...$trailing->toQuery(), 'interval' => Interval::Month->value]),
                'categories' => $this->link('api.v1.analytics.categories', [...$period->toQuery(), 'type' => $type->value]),
            ],
        ]);
    }

    /**
     * @return array{period: array{from: string, to: string}, income: float, expense: float, net: float, transaction_count: int, opening_balance: float, closing_balance: float, daily_average_expense: float}
     */
    private function summaryPayload(User $user, Period $period): array
    {
        $totals = $this->analytics->totals($user, $period);
        $opening = $this->analytics->netBefore($user, $period->from);

        return [
            'period' => $period->toArray(),
            ...$totals,
            'opening_balance' => $opening,
            'closing_balance' => TransactionAnalytics::money($opening + $totals['net']),
            // Averaged over the requested period, never over the server's current day.
            'daily_average_expense' => TransactionAnalytics::money($totals['expense'] / $period->days()),
        ];
    }

    /**
     * @return array{period: array{from: string, to: string}, interval: string, meta: array{opening_balance: float}, data: array<int, array<string, mixed>>}
     */
    private function seriesPayload(User $user, Period $period, Interval $interval): array
    {
        $daily = $this->analytics->dailyTotals($user, $period);

        return [
            'period' => $period->toArray(),
            'interval' => $interval->value,
            'meta' => ['opening_balance' => $this->analytics->netBefore($user, $period->from)],
            'data' => $this->analytics->bucketBy($daily, $period, $interval)->all(),
        ];
    }

    /**
     * @return array{period: array{from: string, to: string}, type: string, meta: array{total: float, category_count: int}, data: array<int, array<string, mixed>>}
     */
    private function categoriesPayload(User $user, Period $period, TransactionType $type, ?int $limit = null): array
    {
        $breakdown = $this->analytics->categoryTotals($user, $period, $type, $limit);

        return [
            'period' => $period->toArray(),
            'type' => $type->value,
            'meta' => [
                'total' => $breakdown['total'],
                'category_count' => $breakdown['category_count'],
            ],
            'data' => $breakdown['data'],
        ];
    }

    /**
     * Validation rules shared by the three range-based endpoints.
     *
     * @return array<string, array<int, string>>
     */
    private function rangeRules(): array
    {
        return [
            'date_from' => ['required', 'date_format:'.Period::DATE_FORMAT],
            'date_to' => ['required', 'date_format:'.Period::DATE_FORMAT, 'after_or_equal:date_from'],
        ];
    }

    /**
     * Build the period and reject anything long enough to make daily bucketing unbounded.
     *
     * @param  array<string, mixed>  $validated
     */
    private function rangeFrom(array $validated): Period
    {
        $period = Period::between($validated['date_from'], $validated['date_to']);

        if ($period->days() > 366) {
            throw ValidationException::withMessages([
                'date_to' => 'The period must not be longer than 366 days.',
            ]);
        }

        return $period;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{href: string, method: string}
     */
    private function link(string $route, array $query = []): array
    {
        return ['href' => route($route, $query), 'method' => 'GET'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'private, max-age=60');
    }
}
