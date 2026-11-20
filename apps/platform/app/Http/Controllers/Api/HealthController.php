<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight liveness endpoint used by the web app, the mobile app,
 * load balancers and uptime checks. It must never touch the database.
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'version' => config('reos.version'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
