<?php

declare(strict_types=1);

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/*
| Laravel gives every unnamed `throttle:N,M` the same counter per person, so
| limits on unrelated routes add up (five invites could block asking for
| seats). Every route's limit has its own counter: `throttle:N,M,<name>`.
*/
it('gives every rate limit its own counter', function () {
    $shared = collect(Route::getRoutes()->getRoutes())
        ->filter(static fn (LaravelRoute $route): bool => collect($route->gatherMiddleware())
            ->contains(static fn (mixed $middleware): bool => is_string($middleware) && preg_match('/^throttle:\d+,\d+$/', $middleware) === 1))
        ->map(static fn (LaravelRoute $route): string => $route->getName() ?? $route->uri())
        ->values()->all();

    expect($shared)->toBe([]);
});
