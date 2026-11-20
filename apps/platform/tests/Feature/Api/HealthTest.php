<?php

declare(strict_types=1);

it('reports the platform as healthy', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('app', config('app.name'))
        ->assertJsonStructure(['status', 'app', 'version', 'time']);
});

it('serves the framework liveness check', function (): void {
    $this->get('/up')->assertOk();
});
