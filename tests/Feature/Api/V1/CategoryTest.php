<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates updates and lists owned categories', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['view', 'create', 'update']);

    $response = $this->postJson('/api/v1/categories', ['name' => 'Food'])
        ->assertCreated()
        ->assertHeader('Location', 'http://127.0.0.1:8000/api/v1/categories/1')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('transactions_count', 0);

    $this->patchJson("/api/v1/categories/{$response->json('id')}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('transactions_count', 0);

    $this->getJson('/api/v1/categories')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/categories?show_inactive=1')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.transactions_count', 0);
});

it('ranks categories by transaction usage', function () {
    $user = User::factory()->create();
    $food = Category::create(['user_id' => $user->id, 'name' => 'Food']);
    $travel = Category::create(['user_id' => $user->id, 'name' => 'Travel']);
    Category::create(['user_id' => $user->id, 'name' => 'Bills']);

    Transaction::factory()->count(3)->create(['user_id' => $user->id, 'category_id' => $travel->id]);
    Transaction::factory()->count(2)->create(['user_id' => $user->id, 'category_id' => $food->id]);
    Sanctum::actingAs($user, ['view']);

    $this->getJson('/api/v1/categories?sort=most_used')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Travel')
        ->assertJsonPath('data.0.transactions_count', 3)
        ->assertJsonPath('data.1.name', 'Food')
        ->assertJsonPath('data.1.transactions_count', 2)
        ->assertJsonPath('data.2.name', 'Bills')
        ->assertJsonPath('data.2.transactions_count', 0);

    $this->getJson('/api/v1/categories')->assertJsonPath('data.0.name', 'Bills');
    $this->getJson('/api/v1/categories?sort=bogus')->assertStatus(422);
});

it('counts only live transactions belonging to the owner', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Food']);
    $otherCategory = Category::create(['user_id' => $other->id, 'name' => 'Food']);

    Transaction::factory()->count(2)->create(['user_id' => $user->id, 'category_id' => $category->id]);
    Transaction::factory()->create(['user_id' => $user->id, 'category_id' => $category->id])->delete();
    Transaction::factory()->count(5)->create(['user_id' => $other->id, 'category_id' => $otherCategory->id]);

    Sanctum::actingAs($user, ['view']);

    $this->getJson("/api/v1/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('transactions_count', 2);

    $this->getJson('/api/v1/categories')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.transactions_count', 2);
});

it('preserves historical transactions when deleting and recreating a category', function () {
    $user = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Travel']);
    $transaction = Transaction::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
    Sanctum::actingAs($user, ['view', 'delete', 'create']);

    $this->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();
    $this->getJson("/api/v1/transactions/{$transaction->id}")
        ->assertJsonPath('category.name', 'Travel')
        ->assertJsonMissingPath('links.category');

    $this->postJson('/api/v1/categories', ['name' => 'Travel'])->assertCreated();
});

it('returns category conflicts and invalid body problem details', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['create']);
    Category::create(['user_id' => $user->id, 'name' => 'Food']);

    $this->postJson('/api/v1/categories', ['name' => 'Food'])
        ->assertConflict()
        ->assertJsonPath('type', '/problems/conflict');

    $this->call('POST', '/api/v1/categories', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'text/plain',
    ], '{}')->assertStatus(415)->assertJsonPath('type', '/problems/unsupported-media-type');

    $this->call('POST', '/api/v1/categories', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], '{')->assertBadRequest()->assertJsonPath('type', '/problems/malformed-json');
});
