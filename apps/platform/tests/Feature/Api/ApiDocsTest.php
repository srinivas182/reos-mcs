<?php

declare(strict_types=1);

it('serves the OpenAPI document locally with the versioned health endpoint', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'REOS API')
        ->assertJsonPath('servers.0.url', 'http://localhost:8000/api/v1')
        ->assertJsonPath('paths./health.get.operationId', 'health')
        ->assertJsonPath('components.schemas.HealthResource.required', ['status', 'app', 'version', 'time']);
});

it('hides the API docs outside local development', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/api.json')->assertForbidden();
})->with(['testing', 'staging', 'production']);

it('matches the committed contract in packages/api-client', function () {
    app()->detectEnvironment(fn () => 'local');

    $committed = json_decode((string) file_get_contents(base_path('../../packages/api-client/openapi.json')), true);

    expect($this->getJson('/docs/api.json')->json())->toEqual($committed);
});
