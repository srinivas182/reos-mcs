<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Push;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;

/** Picks how push is sent from how Expo push runs in Settings › Integrations. */
final readonly class PushSenderFactory
{
    public const INTEGRATION = 'expo-push';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    /** Null when push is off (in production until Expo push is set up). */
    public function make(): ?PushSender
    {
        $mode = $this->mode();

        if ($mode === IntegrationMode::Simulator && ! $this->container->make('app')->isProduction()) {
            return $this->container->make(SimulatorPushSender::class);
        }

        $environment = $mode->environment();

        return $environment === null ? null : new ExpoPushSender($this->container->make(Http::class), $this->vault->get(self::INTEGRATION, $environment));
    }

    private function mode(): IntegrationMode
    {
        $definition = $this->catalogue->find(self::INTEGRATION);

        return $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
    }
}
