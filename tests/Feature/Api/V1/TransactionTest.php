<?php

use App\Models\Category;
use App\Models\Tag;
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

it('creates reads replaces preserves and clears transaction tags', function () {
    $user = User::factory()->create();
    $first = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Alpha', 'description' => 'First tag', 'color' => '#123456']);
    $second = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Beta']);
    Sanctum::actingAs($user, ['view', 'create', 'update']);

    $response = $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => '12.30',
        'transaction_date' => '2026-07-11',
        'tag_ids' => [$second->id, $first->id],
    ])->assertCreated()
        ->assertJsonCount(2, 'tags')
        ->assertJsonPath('tags.0.id', $first->id)
        ->assertJsonPath('tags.0.name', 'Alpha')
        ->assertJsonPath('tags.0.description', 'First tag')
        ->assertJsonPath('tags.0.color', '#123456');
    $transactionId = $response->json('id');

    $this->getJson("/api/v1/transactions/{$transactionId}")
        ->assertOk()->assertJsonPath('tags', $response->json('tags'));
    $this->getJson('/api/v1/transactions')
        ->assertOk()->assertJsonPath('data.0.tags', $response->json('tags'));
    $this->patchJson("/api/v1/transactions/{$transactionId}", ['notes' => 'Keep tags'])
        ->assertOk()->assertJsonCount(2, 'tags');
    $this->patchJson("/api/v1/transactions/{$transactionId}", ['tag_ids' => [$second->id]])
        ->assertOk()->assertJsonCount(1, 'tags')->assertJsonPath('tags.0.id', $second->id);
    $this->assertDatabaseMissing('tag_transaction', ['transaction_id' => $transactionId, 'tag_id' => $first->id]);
    $this->assertDatabaseHas('tag_transaction', ['transaction_id' => $transactionId, 'tag_id' => $second->id]);

    $this->patchJson("/api/v1/transactions/{$transactionId}", ['tag_ids' => []])
        ->assertOk()->assertJsonPath('tags', []);
    $this->assertDatabaseMissing('tag_transaction', ['transaction_id' => $transactionId]);
    $this->assertDatabaseHas('tags', ['id' => $first->id]);
    $this->assertDatabaseHas('tags', ['id' => $second->id]);
});

it('creates untagged transactions when tags are omitted or empty', function (array $attributes) {
    Sanctum::actingAs(User::factory()->create(), ['create']);

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => '1.00',
        'transaction_date' => '2026-07-11',
        ...$attributes,
    ])->assertCreated()->assertJsonPath('tags', []);
    $this->assertDatabaseCount('tag_transaction', 0);
})->with([
    'omitted' => [[]],
    'empty' => [['tag_ids' => []]],
]);

it('rejects invalid transaction tags without changing transactions or assignments', function (string $invalidCase) {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id]);
    $foreignTag = Tag::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id, 'title' => 'Original']);
    $transaction->tags()->attach($tag);
    Sanctum::actingAs($user, ['create', 'update']);
    $invalidTags = match ($invalidCase) {
        'null' => null,
        'scalar' => $tag->id,
        'duplicates' => [$tag->id, $tag->id],
        'foreign' => [$tag->id, $foreignTag->id],
        'missing' => [$tag->id, 999999],
        'non-integer' => ['invalid'],
        'object' => ['tag' => $tag->id],
    };

    $this->postJson('/api/v1/transactions', [
        'type' => 'expense',
        'amount' => '1.00',
        'transaction_date' => '2026-07-11',
        'tag_ids' => $invalidTags,
    ])->assertUnprocessable()->assertJsonPath('type', '/problems/validation-failed');
    $this->patchJson("/api/v1/transactions/{$transaction->id}", [
        'title' => 'Changed',
        'tag_ids' => $invalidTags,
    ])->assertUnprocessable()->assertJsonPath('type', '/problems/validation-failed');

    $this->assertDatabaseCount('transactions', 1);
    expect($transaction->refresh()->title)->toBe('Original')
        ->and($transaction->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
})->with(['null', 'scalar', 'duplicates', 'foreign', 'missing', 'non-integer', 'object']);

it('only exposes owned tags even if a foreign tag was attached to a transaction', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id]);
    $foreignTag = Tag::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach([$tag->id, $foreignTag->id]);
    Sanctum::actingAs($user, ['view', 'update']);

    $this->getJson("/api/v1/transactions/{$transaction->id}")
        ->assertOk()->assertJsonCount(1, 'tags')->assertJsonPath('tags.0.id', $tag->id);
    $this->getJson('/api/v1/transactions')
        ->assertOk()->assertJsonCount(1, 'data.0.tags')->assertJsonPath('data.0.tags.0.id', $tag->id);
    $this->patchJson("/api/v1/transactions/{$transaction->id}", ['notes' => 'Updated'])
        ->assertOk()->assertJsonCount(1, 'tags')->assertJsonPath('tags.0.id', $tag->id);
});

it('retains tag assignments when a transaction is soft deleted', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id]);
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach($tag);
    Sanctum::actingAs($user, ['delete']);

    $this->deleteJson("/api/v1/transactions/{$transaction->id}")->assertNoContent();

    $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    $this->assertDatabaseHas('tag_transaction', ['transaction_id' => $transaction->id, 'tag_id' => $tag->id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});

it('filters transactions by any selected tag without duplicates and preserves pagination filters', function () {
    $user = User::factory()->create();
    $tags = Tag::factory()->count(2)->for($user)->create();
    $first = Transaction::factory()->expense()->for($user)->create(['transaction_date' => '2026-07-10']);
    $second = Transaction::factory()->expense()->for($user)->create(['transaction_date' => '2026-07-11']);
    $both = Transaction::factory()->expense()->for($user)->create(['transaction_date' => '2026-07-12']);
    $first->tags()->attach($tags[0]);
    $second->tags()->attach($tags[1]);
    $both->tags()->attach($tags->modelKeys());
    Transaction::factory()->expense()->for($user)->create();
    Transaction::factory()->income()->for($user)->hasAttached($tags[0])->create();
    Transaction::factory()->expense()->hasAttached($tags[0])->create();
    Sanctum::actingAs($user, ['view']);

    $query = http_build_query(['tag_ids' => $tags->modelKeys(), 'type' => 'expense', 'per_page' => 2]);
    $response = $this->getJson('/api/v1/transactions?'.$query)->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $both->id)
        ->assertJsonPath('data.1.id', $second->id)
        ->assertJsonCount(2, 'data.0.tags');
    $this->getJson($response->json('links.next.href'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('meta.total', 3);
    $this->getJson('/api/v1/transactions?'.http_build_query(['tag_ids' => [$tags[1]->id], 'type' => 'expense']))
        ->assertOk()->assertJsonPath('meta.total', 2);
    $unused = Tag::factory()->for($user)->create();
    $this->getJson('/api/v1/transactions?tag_ids[]='.$unused->id)
        ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
});

it('rejects invalid tag filters', function (string $invalidCase) {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();
    $foreignTag = Tag::factory()->create();
    Sanctum::actingAs($user, ['view']);
    $query = match ($invalidCase) {
        'scalar' => 'tag_ids='.$tag->id,
        'empty' => 'tag_ids[]=',
        'duplicates' => 'tag_ids[]='.$tag->id.'&tag_ids[]='.$tag->id,
        'foreign' => 'tag_ids[]='.$tag->id.'&tag_ids[]='.$foreignTag->id,
        'missing' => 'tag_ids[]=999999',
        'non-integer' => 'tag_ids[]=invalid',
        'object' => 'tag_ids[tag]='.$tag->id,
    };

    $this->getJson('/api/v1/transactions?'.$query)
        ->assertUnprocessable()->assertJsonPath('type', '/problems/validation-failed');
})->with(['scalar', 'empty', 'duplicates', 'foreign', 'missing', 'non-integer', 'object']);
