<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Monitoring\HealthChecks;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public status page (A6.1; "Open status page" on screen 58): whether
 * REOS is up now, each part's state, and uptime over 30 and 90 days. No
 * tenant, person or internal detail.
 */
final class StatusController extends Controller
{
    public function __invoke(): Response
    {
        $latest = HealthChecks::latest();

        return Inertia::render('public/status', [
            'ok' => $latest['ok'] ?? null,
            'parts' => array_map(static fn (array $part): bool => $part['ok'], $latest['parts'] ?? []),
            'checked_at' => $latest['checked_at'] ?? null,
            'uptime' => ['30' => HealthChecks::uptime(30), '90' => HealthChecks::uptime(90)],
        ]);
    }
}
