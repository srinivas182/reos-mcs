<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Runtime settings returned by `GET /api/v1/app-config`.
 *
 * @property-read array{environment: string, release: string, sentry: array{dsn: string}|null, posthog: array{key: string, host: string}|null} $resource
 */
final class AppConfigResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array{environment: string, release: string, sentry: array{dsn: string}|null, posthog: array{key: string, host: string}|null}
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Server environment, used to tag errors and events.
             *
             * @example production
             */
            'environment' => $this->resource['environment'],
            /**
             * Deployed REOS version.
             *
             * @example 0.1.0-dev
             */
            'release' => $this->resource['release'],
            /**
             * Error tracking, or `null` when switched off.
             *
             * @var array{dsn: string}|null
             */
            'sentry' => $this->resource['sentry'],
            /**
             * Product analytics, or `null` when switched off.
             *
             * @var array{key: string, host: string}|null
             */
            'posthog' => $this->resource['posthog'],
        ];
    }
}
