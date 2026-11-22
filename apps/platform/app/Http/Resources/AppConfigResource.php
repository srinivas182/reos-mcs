<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Runtime settings returned by `GET /api/v1/app-config`.
 *
 * @property-read array{environment: string, release: string, sentry: array{dsn: string}|null, posthog: array{key: string, host: string}|null, sign_in: array{google: bool, apple: bool, phone: bool, demo: bool}, realtime: array{key: string, host: string, port: int, scheme: string}|null, preview: array{code: string}|null} $resource
 */
final class AppConfigResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array{environment: string, release: string, sentry: array{dsn: string}|null, posthog: array{key: string, host: string}|null, sign_in: array{google: bool, apple: bool, phone: bool, demo: bool}, realtime: array{key: string, host: string, port: int, scheme: string}|null, preview: array{code: string}|null}
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
            /**
             * Which sign-in buttons to show. `demo` means Google and Apple run
             * on demo data: sign in through the browser's persona picker.
             *
             * @var array{google: bool, apple: bool, phone: bool, demo: bool}
             */
            'sign_in' => $this->resource['sign_in'],
            /**
             * Live messages (B6.1): the public Reverb key and where to connect,
             * or `null` when live updates are off (the app refreshes instead).
             *
             * @var array{key: string, host: string, port: int, scheme: string}|null
             */
            'realtime' => $this->resource['realtime'],
            /**
             * The private preview for usability checks (P7.0): show the preview
             * banner, with the one code every verification uses. `null` elsewhere.
             *
             * @var array{code: string}|null
             */
            'preview' => $this->resource['preview'],
        ];
    }
}
