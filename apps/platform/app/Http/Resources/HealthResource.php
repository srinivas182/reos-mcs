<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Liveness payload returned by `GET /api/v1/health`.
 *
 * @property-read array{app: string, version: string, time: \DateTimeInterface} $resource
 */
final class HealthResource extends JsonResource
{
    /**
     * Health is a bare object, not wrapped in `data`, so load balancers
     * and uptime monitors can read `status` directly.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array{status: 'ok', app: string, version: string, time: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** Always `ok` when the application can serve requests. */
            'status' => 'ok',
            /**
             * Application name.
             *
             * @example REOS
             */
            'app' => $this->resource['app'],
            /**
             * Deployed REOS version.
             *
             * @example 0.1.0-dev
             */
            'version' => $this->resource['version'],
            /**
             * Server time (ISO 8601).
             *
             * @format date-time
             */
            'time' => $this->resource['time']->format(DATE_ATOM),
        ];
    }
}
