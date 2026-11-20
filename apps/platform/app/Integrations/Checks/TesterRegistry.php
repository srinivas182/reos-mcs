<?php

declare(strict_types=1);

namespace App\Integrations\Checks;

use App\Integrations\Checks\Testers\GrafanaOtlpTester;
use App\Integrations\Checks\Testers\KeysPresentTester;
use App\Integrations\Checks\Testers\OAuthClientTester;
use App\Integrations\Checks\Testers\PostHogTester;
use App\Integrations\Checks\Testers\SentryTester;
use App\Integrations\Checks\Testers\StripeTester;
use App\Integrations\Checks\Testers\TwilioTester;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory;

/**
 * Which tester checks which integration. The register stays the list of
 * vendors; this only says how to test the ones with vendor-specific code.
 * Every other integration uses {@see KeysPresentTester} until its own
 * sprint adds a tester here.
 */
final class TesterRegistry
{
    /** @var array<string, ConnectionTester> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Integration id => how to build its tester.
     *
     * @return array<string, Closure(Container): ConnectionTester>
     */
    public static function testers(): array
    {
        $oauth = static fn (string $tokenUrl): Closure => static fn (Container $c): ConnectionTester => new OAuthClientTester($c->make(Factory::class), $tokenUrl);

        return [
            'google-workspace' => $oauth('https://oauth2.googleapis.com/token'),
            'microsoft-365' => $oauth('https://login.microsoftonline.com/common/oauth2/v2.0/token'),
            'stripe' => static fn (Container $c): ConnectionTester => $c->make(StripeTester::class),
            'stripe-connect' => static fn (Container $c): ConnectionTester => $c->make(StripeTester::class),
            'twilio-sms' => static fn (Container $c): ConnectionTester => $c->make(TwilioTester::class),
            'sentry' => static fn (Container $c): ConnectionTester => $c->make(SentryTester::class),
            'posthog' => static fn (Container $c): ConnectionTester => $c->make(PostHogTester::class),
            'grafana-otel' => static fn (Container $c): ConnectionTester => $c->make(GrafanaOtlpTester::class),
        ];
    }

    public function for(string $integrationId): ConnectionTester
    {
        if (isset($this->resolved[$integrationId])) {
            return $this->resolved[$integrationId];
        }

        $factory = self::testers()[$integrationId] ?? null;

        return $this->resolved[$integrationId] = $factory !== null
            ? $factory($this->container)
            : $this->container->make(KeysPresentTester::class);
    }
}
