<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('enforces authentication and token abilities', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);

    $this->getJson('/api/v1/transactions')
        ->assertUnauthorized()
        ->assertJsonPath('type', '/problems/unauthenticated');

    Sanctum::actingAs($user, ['create']);
    $this->getJson('/api/v1/transactions')->assertForbidden();

    Sanctum::actingAs($user, ['view']);
    $this->getJson('/api/v1/transactions')->assertOk();

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => '1.00',
        'transaction_date' => '2026-07-18',
    ])->assertForbidden()
        ->assertJsonPath('type', '/problems/insufficient-ability');
    $this->patchJson("/api/v1/transactions/{$transaction->id}", ['notes' => 'Nope'])->assertForbidden();
    $this->deleteJson("/api/v1/transactions/{$transaction->id}")->assertForbidden();
});

it('creates updates lists and deletes owned transactions', function () {
    $user = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Food']);
    Sanctum::actingAs($user, ['view', 'create', 'update', 'delete']);

    $response = $this->postJson('/api/v1/transactions', [
        'title' => 'Lunch',
        'type' => 'expense',
        'amount' => '12.30',
        'category_id' => $category->id,
        'transaction_date' => '2026-07-11',
    ])->assertCreated()
        ->assertHeader('Location', 'http://127.0.0.1:8000/api/v1/transactions/1')
        ->assertJsonPath('type', 'expense')
        ->assertJsonPath('amount', '12.30')
        ->assertJsonPath('category.name', 'Food');
    $transactionId = $response->json('id');

    $this->patchJson("/api/v1/transactions/{$transactionId}", ['notes' => 'Team lunch'])
        ->assertOk()
        ->assertJsonPath('notes', 'Team lunch');

    $this->getJson('/api/v1/transactions?type=expense&per_page=1')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.type', 'expense')
        ->assertJsonPath('links.self.href', 'http://127.0.0.1:8000/api/v1/transactions?type=expense&per_page=1&page=1');

    $this->deleteJson("/api/v1/transactions/{$transactionId}")->assertNoContent();
    $this->getJson("/api/v1/transactions/{$transactionId}")->assertNotFound();
});

it('isolates transactions between users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($other, ['view']);

    $this->getJson("/api/v1/transactions/{$transaction->id}")
        ->assertNotFound()
        ->assertJsonPath('detail', 'The requested transaction was not found.');
});

it('validates transaction filters and writes as problem details', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($user, ['view', 'create']);
    $inactive = Category::create(['user_id' => $user->id, 'name' => 'Inactive', 'is_active' => false]);
    $otherCategory = Category::create(['user_id' => $other->id, 'name' => 'Other']);

    $this->getJson('/api/v1/transactions?per_page=101')
        ->assertUnprocessable()
        ->assertJsonPath('type', '/problems/validation-failed')
        ->assertJsonStructure(['errors' => ['per_page']]);
    $this->getJson('/api/v1/transactions?type=savings')
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['type']]);
    $this->postJson('/api/v1/transactions', ['amount' => '1.234'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    foreach ([$inactive->id, $otherCategory->id] as $categoryId) {
        $this->postJson('/api/v1/transactions', [
            'type' => 'expense',
            'amount' => '1.00',
            'category_id' => $categoryId,
            'transaction_date' => '2026-07-18',
        ])->assertUnprocessable()->assertJsonStructure(['errors' => ['category_id']]);
    }
});

it('filters and stably orders paginated transactions', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['view']);
    $older = Transaction::factory()->expense()->create([
        'user_id' => $user->id,
        'transaction_date' => '2026-07-10',
    ]);
    $newer = Transaction::factory()->expense()->create([
        'user_id' => $user->id,
        'transaction_date' => '2026-07-11',
    ]);
    Transaction::factory()->income()->create([
        'user_id' => $user->id,
        'transaction_date' => '2026-07-12',
    ]);

    $this->getJson('/api/v1/transactions?type=expense&date_from=2026-07-10&date_to=2026-07-11&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('links.next.href', 'http://127.0.0.1:8000/api/v1/transactions?type=expense&date_from=2026-07-10&date_to=2026-07-11&per_page=1&page=2');

    expect($older->id)->toBeLessThan($newer->id);
});
