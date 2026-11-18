<?php

use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('creates reads updates and clears owned tag metadata', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($user, ['view', 'create', 'update']);

    $response = $this->postJson('/api/v1/tags', [
        'name' => 'Holiday', 'description' => 'Summer', 'color' => '#aAbB12', 'user_id' => $other->id,
    ])->assertCreated()->assertJsonPath('transactions_count', 0)->assertJsonPath('color', '#aAbB12');
    $id = $response->json('id');
    $response->assertHeader('Location', route('api.v1.tags.show', $id));
    expect(Tag::findOrFail($id)->user_id)->toBe($user->id);
    $this->getJson("/api/v1/tags/$id")->assertOk()->assertJsonPath('description', 'Summer');
    $this->patchJson("/api/v1/tags/$id", ['name' => 'Holiday', 'user_id' => $other->id])
        ->assertOk()->assertJsonPath('description', 'Summer')->assertJsonPath('color', '#aAbB12');
    $this->patchJson("/api/v1/tags/$id", ['description' => null, 'color' => null])
        ->assertOk()->assertJsonPath('description', null)->assertJsonPath('color', null);
    expect(Tag::findOrFail($id)->user_id)->toBe($user->id);
    $this->postJson('/api/v1/tags', ['name' => 'Default'])
        ->assertCreated()->assertJsonPath('color', null)->assertJsonPath('description', null);
});

it('scopes tag names and returns tag conflict details', function () {
    $user = User::factory()->create();
    Tag::factory()->create(['name' => 'Shared']);
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Other']);
    Sanctum::actingAs($user, ['create', 'update']);
    $this->postJson('/api/v1/tags', ['name' => 'Shared'])->assertCreated();
    $this->postJson('/api/v1/tags', ['name' => 'Shared'])->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('detail', 'The tag name conflicts with an existing tag.');
    $this->patchJson("/api/v1/tags/{$tag->id}", ['name' => 'Shared'])->assertConflict();
    expect($tag->fresh()->name)->toBe('Other');
});

it('hides foreign and missing tags from all item operations', function (string $method) {
    $tag = Tag::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['view', 'update', 'delete']);
    foreach ([$tag->id, 999999] as $id) {
        $this->{$method}("/api/v1/tags/$id", ['name' => 'Changed'])->assertNotFound()
            ->assertJsonPath('detail', 'The requested tag was not found.');
    }
    expect($tag->fresh()->name)->toBe($tag->name);
})->with(['getJson', 'patchJson', 'deleteJson']);

it('requires authentication and the matching tag ability', function (string $method, string $suffix) {
    $tag = Tag::factory()->create();
    $url = '/api/v1/tags'.($suffix ? "/{$tag->id}" : '');
    $this->{$method}($url, ['name' => 'New'])->assertUnauthorized();
    Sanctum::actingAs($tag->user, []);
    $this->{$method}($url, ['name' => 'New'])->assertForbidden();
})->with([
    ['getJson', ''], ['postJson', ''], ['getJson', 'item'], ['patchJson', 'item'], ['deleteJson', 'item'],
]);

it('validates tag writes without changing existing values', function (string $field, mixed $value) {
    $tag = Tag::factory()->create();
    Sanctum::actingAs($tag->user, ['create', 'update']);
    $this->postJson('/api/v1/tags', [$field => $value, ...($field === 'name' ? [] : ['name' => 'New'])])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->patchJson("/api/v1/tags/{$tag->id}", [$field => $value])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($tag->fresh()->getAttributes())->toEqual($tag->getAttributes());
})->with([
    ['name', null], ['name', ''], ['name', str_repeat('n', 256)], ['name', []],
    ['description', str_repeat('d', 256)], ['description', []],
    ['color', '#123'], ['color', '123456'], ['color', '#gggggg'], ['color', []],
]);

it('requires a name and valid JSON tag request bodies', function () {
    Sanctum::actingAs(User::factory()->create(), ['create']);
    $this->postJson('/api/v1/tags', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->call('POST', '/api/v1/tags', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{}')->assertStatus(415);
    $this->call('POST', '/api/v1/tags', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{')->assertBadRequest();
});

it('paginates owned tags by ID and counts live transactions', function () {
    $user = User::factory()->create();
    $first = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Zulu']);
    $popular = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);
    Tag::factory()->create(['name' => 'Foreign']);
    $transactions = Transaction::factory()->count(3)->create(['user_id' => $user->id]);
    $popular->transactions()->attach($transactions->modelKeys());
    $transactions->first()->delete();
    Sanctum::actingAs($user, ['view']);
    $this->getJson('/api/v1/tags?per_page=1')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
    foreach (['name', 'most_used'] as $sort) {
        $this->getJson('/api/v1/tags?sort='.$sort.'&per_page=1')->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('links.next.href', route('api.v1.tags.index').'?per_page=1&page=2');
    }
    $this->getJson('/api/v1/tags?per_page=1&page=2')->assertOk()
        ->assertJsonPath('data.0.id', $popular->id)->assertJsonPath('data.0.transactions_count', 2);
    $this->getJson("/api/v1/tags/{$popular->id}")->assertOk()->assertJsonPath('transactions_count', 2);
});

it('validates tag list query parameters', function (string $query) {
    Sanctum::actingAs(User::factory()->create(), ['view']);
    $this->getJson('/api/v1/tags?'.$query)->assertUnprocessable();
})->with(['page=0', 'page=abc', 'per_page=0', 'per_page=101']);

it('deletes tags and their pivots while preserving transactions', function () {
    $tag = Tag::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $tag->user_id]);
    $tag->transactions()->attach($transaction);
    Sanctum::actingAs($tag->user, ['delete']);
    $this->deleteJson("/api/v1/tags/{$tag->id}")->assertNoContent();
    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('tag_transaction', ['tag_id' => $tag->id]);
    expect($transaction->fresh())->not->toBeNull();
});

it('returns conflicts when duplicate tag names race a write', function (bool $updating) {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Original']);
    Sanctum::actingAs($user, ['create', 'update']);
    $event = 'eloquent.'.($updating ? 'updating' : 'creating').': '.Tag::class;
    Event::listen($event, function () use ($user): void {
        DB::table('tags')->insert([
            'user_id' => $user->id, 'name' => 'Racing', 'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    try {
        if ($updating) {
            $this->patchJson("/api/v1/tags/{$tag->id}", ['name' => 'Racing'])->assertConflict();
            expect($tag->fresh()->name)->toBe('Original');
        } else {
            $this->postJson('/api/v1/tags', ['name' => 'Racing'])->assertConflict();
        }
    } finally {
        Event::forget($event);
    }
})->with([false, true]);
