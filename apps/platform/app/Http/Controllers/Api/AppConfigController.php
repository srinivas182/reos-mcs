<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppConfigResource;
use App\Monitoring\MonitoringRuntime;
use App\Services\Identity\SignInOptions;
use App\Support\Preview;

/**
 * Settings the phone app reads when it starts: which error tracking and
 * analytics are switched on (Settings › Integrations), so turning a service
 * on or off needs no app release.
 */
final class AppConfigController extends Controller
{
    /**
     * Get the app's runtime settings.
     *
     * Public values only (Sentry DSN, PostHog project key, the Reverb key), the
     * same ones a browser receives. `null` means the service is switched off.
     *
     * @unauthenticated
     */
    public function __invoke(MonitoringRuntime $runtime, SignInOptions $signIn): AppConfigResource
    {
        $options = $signIn->available();

        return new AppConfigResource([
            ...$runtime->clientConfig('mobile'),
            'sign_in' => [
                ...$options,
                'demo' => $signIn->demo('google-sign-in') || $signIn->demo('apple-sign-in'),
            ],
            'realtime' => self::realtime(),
            'preview' => Preview::on() ? ['code' => Preview::code()] : null,
        ]);
    }

    /**
     * The public side of Reverb (B6.1); the secret never leaves the server.
     *
     * @return array{key: string, host: string, port: int, scheme: string}|null
     */
    private static function realtime(): ?array
    {
        $key = config('broadcasting.connections.reverb.key');
        $options = config('broadcasting.connections.reverb.public') ?? config('broadcasting.connections.reverb.options');

        if (config('broadcasting.default') !== 'reverb' || ! is_string($key) || $key === '' || ! is_array($options)) {
            return null;
        }

        $host = $options['host'] ?? null;
        $port = $options['port'] ?? null;
        $scheme = $options['scheme'] ?? null;

        return [
            'key' => $key,
            'host' => is_string($host) ? $host : 'localhost',
            'port' => is_numeric($port) ? (int) $port : 443,
            'scheme' => is_string($scheme) ? $scheme : 'https',
        ];
    }
}
