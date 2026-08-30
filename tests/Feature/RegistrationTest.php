<?php

use App\Models\User;
use Laravel\Jetstream\Jetstream;

$payload = fn () => [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password',
    'password_confirmation' => 'password',
    'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
];

test('registration screen is rendered during first-user bootstrap', function () {
    config()->set('app.enable_registration', false);

    $this->get('/register')->assertStatus(200);
});

test('registration screen is rendered in multi-user mode', function () {
    config()->set('app.enable_registration', true);
    User::factory()->create();

    $this->get('/register')->assertStatus(200);
});

test('registration screen is not rendered once a user exists', function () {
    config()->set('app.enable_registration', false);
    User::factory()->create();

    $this->get('/register')->assertStatus(404);
});

test('new users can register during first-user bootstrap', function () use ($payload) {
    config()->set('app.enable_registration', false);

    $response = $this->post('/register', $payload());

    $this->assertAuthenticated();
    $response->assertRedirect(config('fortify.home'));
    expect(User::count())->toBe(1);
});

test('new users can register in multi-user mode', function () use ($payload) {
    config()->set('app.enable_registration', true);
    User::factory()->create();

    $response = $this->post('/register', $payload());

    $this->assertAuthenticated();
    $response->assertRedirect(config('fortify.home'));
    expect(User::count())->toBe(2);
});

test('registration is blocked once a user exists', function () use ($payload) {
    config()->set('app.enable_registration', false);
    User::factory()->create();

    $response = $this->post('/register', $payload());

    $response->assertStatus(404);
    $this->assertGuest();
    expect(User::count())->toBe(1);
});
