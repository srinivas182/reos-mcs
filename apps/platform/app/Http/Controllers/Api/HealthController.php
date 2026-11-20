<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HealthResource;

/**
 * Lightweight liveness endpoint used by the web app, the mobile app,
 * load balancers and uptime checks. It must never touch the database.
 */
final class HealthController extends Controller
{
    /**
     * Check the API is up.
     *
     * Returns the application name, deployed version and server time.
     * Needs no authentication and never touches the database.
     *
     * @unauthenticated
     */
    public function __invoke(): HealthResource
    {
        return new HealthResource([
            'app' => config()->string('app.name'),
            'version' => config()->string('reos.version'),
            'time' => now(),
        ]);
    }
}
