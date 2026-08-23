<?php

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

/**
 * Every analytics endpoint, with the query string that makes it valid.
 */
function analyticsEndpoints(): array
{
    return [
        '/api/v1/analytics/series?date_from=2026-07-01&date_to=2026-07-31',
        '/api/v1/analytics/categories?date_from=2026-07-01&date_to=2026-07-31&type=expense',
        '/api/v1/analytics/overview?month=2026-07',
    ];
}

function analyticsTransaction(User $user, TransactionType $type, string $amount, string $date, ?Category $category = null): Transaction
{
    return Transaction::create([
        'user_id' => $user->id,
        'category_id' => $category?->id,
        'title' => ucfirst($type->value),
        'type' => $type,
        'amount' => $amount,
        'transaction_date' => $date,
    ]);
}

/**
 * Fixed fixture: 600.00 carried in from June, a busy July, and a quiet August.
 */
function analyticsFixture(User $user): void
{
    analyticsTransaction($user, TransactionType::Income, '1000.00', '2026-06-15');
    analyticsTransaction($user, TransactionType::Expense, '400.00', '2026-06-20');

    analyticsTransaction($user, TransactionType::Income, '3000.00', '2026-07-05');
    analyticsTransaction($user, TransactionType::Expense, '100.50', '2026-07-05');
    analyticsTransaction($user, TransactionType::Expense, '200.25', '2026-07-20');

    analyticsTransaction($user, TransactionType::Income, '500.00', '2026-08-10');
    analyticsTransaction($user, TransactionType::Expense, '50.00', '2026-08-10');
}

it('enforces authentication and token abilities on every analytics endpoint', function () {
    $user = User::factory()->create();

    foreach (analyticsEndpoints() as $endpoint) {
        $this->getJson($endpoint)
            ->assertUnauthorized()
            ->assertJsonPath('type', '/problems/unauthenticated');
    }

    Sanctum::actingAs($user, ['create']);

    foreach (analyticsEndpoints() as $endpoint) {
        $this->getJson($endpoint)
            ->assertForbidden()
            ->assertJsonPath('type', '/problems/insufficient-ability');
    }

    Sanctum::actingAs($user, ['view']);

    foreach (analyticsEndpoints() as $endpoint) {
        $this->getJson($endpoint)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, private');
    }
});

it('never leaks another user analytics', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherCategory = Category::create(['user_id' => $other->id, 'name' => 'Not Mine']);

    analyticsFixture($user);
    analyticsTransaction($other, TransactionType::Income, '9999.00', '2026-06-01');
    analyticsTransaction($other, TransactionType::Income, '8888.00', '2026-07-07', $otherCategory);
    analyticsTransaction($other, TransactionType::Expense, '7777.00', '2026-07-08', $otherCategory);

    Sanctum::actingAs($user, ['view']);

    $this->getJson('/api/v1/analytics/series?date_from=2026-07-01&date_to=2026-07-31&interval=month')
        ->assertOk()
        ->assertJsonPath('data.0.income', 3000)
        ->assertJsonPath('meta.opening_balance', 600);

    $categories = $this->getJson('/api/v1/analytics/categories?date_from=2026-07-01&date_to=2026-07-31&type=expense')
        ->assertOk()
        ->assertJsonPath('meta.total', 300.75);

    expect(collect($categories->json('data'))->pluck('category_name'))->not->toContain('Not Mine');

    $overview = $this->getJson('/api/v1/analytics/overview?month=2026-07')->assertOk();

    expect($overview->json('summary.expense'))->toBe(300.75)
        ->and($overview->json('categories.meta.total'))->toBe(300.75);
});

it('zero fills every series bucket in range', function () {
    $user = User::factory()->create();
    analyticsFixture($user);
    Sanctum::actingAs($user, ['view']);

    $this->getJson('/api/v1/analytics/series?date_from=2026-07-01&date_to=2026-09-30&interval=month')
        ->assertOk()
        ->assertJsonPath('interval', 'month')
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.opening_balance', 600)
        ->assertJsonPath('data.0.period', '2026-07')
        ->assertJsonPath('data.0.period_start', '2026-07-01')
        ->assertJsonPath('data.0.period_end', '2026-07-31')
        ->assertJsonPath('data.0.net', 2699.25)
        ->assertJsonPath('data.1.period', '2026-08')
        ->assertJsonPath('data.1.income', 500)
        ->assertJsonPath('data.1.expense', 50)
        ->assertJsonPath('data.2.period', '2026-09')
        ->assertJsonPath('data.2.income', 0)
        ->assertJsonPath('data.2.expense', 0)
        ->assertJsonPath('data.2.net', 0);

    $this->getJson('/api/v1/analytics/series?date_from=2026-07-04&date_to=2026-07-06&interval=day')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.period', '2026-07-04')
        ->assertJsonPath('data.0.period_start', '2026-07-04')
        ->assertJsonPath('data.0.period_end', '2026-07-04')
        ->assertJsonPath('data.0.expense', 0)
        ->assertJsonPath('data.1.period', '2026-07-05')
        ->assertJsonPath('data.1.income', 3000)
        ->assertJsonPath('data.1.expense', 100.5)
        ->assertJsonPath('data.2.expense', 0);

    // Weekly buckets are clamped to the requested period at both edges.
    $this->getJson('/api/v1/analytics/series?date_from=2026-07-01&date_to=2026-07-14&interval=week')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.period_start', '2026-07-01')
        ->assertJsonPath('data.0.income', 3000)
        ->assertJsonPath('data.1.period_start', '2026-07-06')
        ->assertJsonPath('data.1.period_end', '2026-07-12')
        ->assertJsonPath('data.2.period_end', '2026-07-14');

    // Defaults to monthly when no interval is given.
    $this->getJson('/api/v1/analytics/series?date_from=2026-07-01&date_to=2026-07-31')
        ->assertOk()
        ->assertJsonPath('interval', 'month')
        ->assertJsonCount(1, 'data');
});

it('breaks spending down by category', function () {
    $user = User::factory()->create();
    $food = Category::create(['user_id' => $user->id, 'name' => 'Food']);
    $transport = Category::create(['user_id' => $user->id, 'name' => 'Transport']);

    analyticsTransaction($user, TransactionType::Expense, '300.00', '2026-08-02', $food);
    analyticsTransaction($user, TransactionType::Expense, '320.10', '2026-08-03', $food);
    analyticsTransaction($user, TransactionType::Expense, '200.00', '2026-08-04', $transport);
    analyticsTransaction($user, TransactionType::Expense, '100.00', '2026-08-05');
    analyticsTransaction($user, TransactionType::Income, '5000.00', '2026-08-06', $food);

    Sanctum::actingAs($user, ['view']);

    $response = $this->getJson('/api/v1/analytics/categories?date_from=2026-08-01&date_to=2026-08-31&type=expense')
        ->assertOk()
        ->assertJsonPath('type', 'expense')
        ->assertJsonPath('meta.total', 920.1)
        ->assertJsonPath('meta.category_count', 3)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.category_id', $food->id)
        ->assertJsonPath('data.0.category_name', 'Food')
        ->assertJsonPath('data.0.total', 620.1)
        ->assertJsonPath('data.1.category_name', 'Transport')
        ->assertJsonPath('data.1.total', 200)
        ->assertJsonPath('data.2.category_id', null)
        ->assertJsonPath('data.2.category_name', 'Uncategorized')
        ->assertJsonPath('data.2.total', 100);

    expect(array_sum(array_column($response->json('data'), 'share')))->toBeGreaterThan(0.999)
        ->and(array_sum(array_column($response->json('data'), 'share')))->toBeLessThan(1.001);

    // The limit truncates the rows but never the grand total, so the client can
    // derive its own "Others" slice.
    $this->getJson('/api/v1/analytics/categories?date_from=2026-08-01&date_to=2026-08-31&type=expense&limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category_name', 'Food')
        ->assertJsonPath('meta.total', 920.1)
        ->assertJsonPath('meta.category_count', 3);

    $this->getJson('/api/v1/analytics/categories?date_from=2026-08-01&date_to=2026-08-31&type=income')
        ->assertOk()
        ->assertJsonPath('meta.total', 5000)
        ->assertJsonPath('meta.category_count', 1)
        ->assertJsonPath('data.0.category_name', 'Food');
});

it('bundles a whole dashboard into the overview', function () {
    $user = User::factory()->create();
    $food = Category::create(['user_id' => $user->id, 'name' => 'Food']);
    $rent = Category::create(['user_id' => $user->id, 'name' => 'Rent']);

    analyticsFixture($user);
    analyticsTransaction($user, TransactionType::Expense, '75.00', '2026-07-09', $rent);
    analyticsTransaction($user, TransactionType::Expense, '25.00', '2026-08-11', $food);

    Sanctum::actingAs($user, ['view']);

    $this->getJson('/api/v1/analytics/overview?month=2026-08')
        ->assertOk()
        ->assertJsonStructure(['summary', 'series', 'categories', 'previous_categories', 'links'])
        ->assertJsonPath('month', '2026-08')
        ->assertJsonPath('period.from', '2026-08-01')
        ->assertJsonPath('period.to', '2026-08-31')
        ->assertJsonPath('summary.income', 500)
        ->assertJsonPath('summary.expense', 75)
        ->assertJsonPath('summary.net', 425)
        ->assertJsonPath('series.interval', 'month')
        ->assertJsonPath('series.period.from', '2025-09-01')
        ->assertJsonPath('series.period.to', '2026-08-31')
        ->assertJsonCount(12, 'series.data')
        ->assertJsonPath('categories.period.from', '2026-08-01')
        ->assertJsonPath('categories.meta.total', 75)
        ->assertJsonPath('categories.data.0.category_name', 'Uncategorized')
        ->assertJsonPath('previous_categories.period.from', '2026-07-01')
        ->assertJsonPath('previous_categories.period.to', '2026-07-31')
        ->assertJsonPath('previous_categories.meta.total', 375.75)
        ->assertJsonPath('previous_categories.data.0.category_name', 'Uncategorized')
        ->assertJsonPath('previous_categories.data.1.category_name', 'Rent')
        ->assertJsonPath('links.self.href', 'http://127.0.0.1:8000/api/v1/analytics/overview?month=2026-08');

    // Without a month it falls back to the current server month.
    $this->getJson('/api/v1/analytics/overview')
        ->assertOk()
        ->assertJsonPath('month', CarbonImmutable::now()->format('Y-m'));
});

it('resolves the overview month independently of the server clock', function () {
    $user = User::factory()->create();

    analyticsTransaction($user, TransactionType::Expense, '10.00', '2026-02-10');
    analyticsTransaction($user, TransactionType::Expense, '20.00', '2026-01-15');

    Sanctum::actingAs($user, ['view']);

    // On a 31st, a naive "Y-m" parse fills the missing day from today and overflows a short
    // month into the next one, which also collapses previous_categories onto the same month.
    $this->travelTo(CarbonImmutable::parse('2026-03-31'));

    $this->getJson('/api/v1/analytics/overview?month=2026-02')
        ->assertOk()
        ->assertJsonPath('period.from', '2026-02-01')
        ->assertJsonPath('period.to', '2026-02-28')
        ->assertJsonPath('summary.expense', 10)
        ->assertJsonPath('categories.meta.total', 10)
        ->assertJsonPath('previous_categories.period.from', '2026-01-01')
        ->assertJsonPath('previous_categories.period.to', '2026-01-31')
        ->assertJsonPath('previous_categories.meta.total', 20);
});

it('rejects malformed analytics query parameters', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['view']);

    $cases = [
        ['/api/v1/analytics/series?date_from=2026-07-01&date_to=2026-07-31&interval=fortnight', ['interval']],
        ['/api/v1/analytics/categories?date_from=2026-07-01&date_to=2026-07-31', ['type']],
        ['/api/v1/analytics/categories?date_from=2026-07-01&date_to=2026-07-31&type=savings', ['type']],
        ['/api/v1/analytics/categories?date_from=2026-07-01&date_to=2026-07-31&type=expense&limit=101', ['limit']],
        ['/api/v1/analytics/overview?month=2026-8', ['month']],
    ];

    foreach ($cases as [$endpoint, $fields]) {
        $this->getJson($endpoint)
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/validation-failed')
            ->assertJsonStructure(['errors' => $fields]);
    }

});
