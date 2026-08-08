<?php

use Illuminate\Support\Facades\DB;

/**
 * Set the four build metadata values the Docker build normally injects.
 */
function stampBuild(?string $releaseTag, ?string $refName, ?string $commitSha, ?string $repositoryUrl): void
{
    config()->set('app.release_tag', $releaseTag);
    config()->set('app.ref_name', $refName);
    config()->set('app.commit_sha', $commitSha);
    config()->set('app.repository_url', $repositoryUrl);
}

it('identifies the instance without authentication', function () {
    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertJsonPath('application', 'wiwit');
});

it('ignores a bearer token entirely', function () {
    $this->withHeader('Authorization', 'Bearer totally-invalid')
        ->getJson('/api/v1/instance')
        ->assertOk()
        ->assertJsonPath('application', 'wiwit');
});

it('exposes the configured instance name', function () {
    config()->set('app.name', 'Wiwit Demo');

    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertJsonPath('instance_name', 'Wiwit Demo');
});

it('reports release build metadata', function () {
    stampBuild('v1.4.0', 'main', '9f1c0b3e2a7d41f8c6b0e5a2d3f4c5b6a7089123', 'https://github.com/wiwit-app/wiwit');

    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertJsonPath('version.display', 'v1.4.0')
        ->assertJsonPath('version.release_tag', 'v1.4.0')
        ->assertJsonPath('version.commit_sha', '9f1c0b3e2a7d41f8c6b0e5a2d3f4c5b6a7089123')
        ->assertJsonPath('version.repository_url', 'https://github.com/wiwit-app/wiwit');
});

it('reports commit build metadata when there is no release tag', function () {
    stampBuild(null, 'main', '9f1c0b3e2a7d41f8c6b0e5a2d3f4c5b6a7089123', 'https://github.com/wiwit-app/wiwit');

    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertJsonPath('version.display', 'Ver. main@9f1c0b3')
        ->assertJsonPath('version.release_tag', null);
});

it('reports an unknown version when no build metadata is present', function () {
    stampBuild(null, null, null, null);

    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertJsonPath('application', 'wiwit')
        ->assertJsonPath('version.display', null)
        ->assertJsonPath('version.release_tag', null)
        ->assertJsonPath('version.ref_name', null)
        ->assertJsonPath('version.commit_sha', null)
        ->assertJsonPath('version.repository_url', null);
});

it('treats empty build metadata as unknown', function () {
    stampBuild('', '', '', '');

    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertJsonPath('application', 'wiwit')
        ->assertJsonPath('version.display', null)
        ->assertJsonPath('version.release_tag', null)
        ->assertJsonPath('version.ref_name', null)
        ->assertJsonPath('version.commit_sha', null)
        ->assertJsonPath('version.repository_url', null);
});

it('returns exactly the documented payload', function () {
    stampBuild('v1.4.0', 'main', '9f1c0b3e2a7d41f8c6b0e5a2d3f4c5b6a7089123', 'https://github.com/wiwit-app/wiwit');
    config()->set('app.name', 'Wiwit');

    // Guards a public pre-auth endpoint against field creep.
    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertExactJson([
            'application' => 'wiwit',
            'instance_name' => 'Wiwit',
            'version' => [
                'display' => 'v1.4.0',
                'release_tag' => 'v1.4.0',
                'ref_name' => 'main',
                'commit_sha' => '9f1c0b3e2a7d41f8c6b0e5a2d3f4c5b6a7089123',
                'repository_url' => 'https://github.com/wiwit-app/wiwit',
            ],
        ]);
});

it('serves a cacheable response', function () {
    $response = $this->getJson('/api/v1/instance')->assertOk();

    expect($response->headers->getCacheControlDirective('max-age'))->toBe('60')
        ->and($response->headers->getCacheControlDirective('public'))->toBeTrue();
});

it('answers without touching the database', function () {
    DB::connection()->enableQueryLog();

    $this->getJson('/api/v1/instance')->assertOk();

    expect(DB::connection()->getQueryLog())->toBeEmpty();
});

it('rejects other http methods with problem details', function () {
    $this->postJson('/api/v1/instance')
        ->assertStatus(405)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', '/problems/method-not-allowed')
        ->assertJsonPath('status', 405);
});
