<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppConfigResource;
use App\Monitoring\MonitoringRuntime;
use App\Services\Identity\SignInOptions;

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
     * Public values only (Sentry DSN, PostHog project key), the same ones a
     * browser receives. `null` means the service is switched off.
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
        ]);
    }
}
