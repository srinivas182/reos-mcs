<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;

/** Picks how texts are sent from how Twilio SMS runs in Settings › Integrations. */
final readonly class TextMessagesFactory
{
    public const INTEGRATION = 'twilio-sms';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    /** Whether texts can go out at all (off in production until Twilio is set up). */
    public function available(): bool
    {
        return $this->mode() !== IntegrationMode::Off && ($this->mode() !== IntegrationMode::Simulator || ! $this->container->make('app')->isProduction());
    }

    public function make(): ?TextMessages
    {
        $mode = $this->mode();

        if ($mode === IntegrationMode::Simulator && ! $this->container->make('app')->isProduction()) {
            return $this->container->make(SimulatorTextMessages::class);
        }

        $environment = $mode->environment();

        return $environment === null ? null : new TwilioTextMessages($this->container->make(Http::class), $this->vault->get(self::INTEGRATION, $environment));
    }

    /**
     * The Twilio auth token that signs inbound texts (STOP, HELP); null on
     * the simulator, which takes unsigned test replies outside production.
     */
    public function authToken(): ?string
    {
        $mode = $this->mode();

        if ($mode === IntegrationMode::Simulator && ! $this->container->make('app')->isProduction()) {
            return null;
        }

        $environment = $mode->environment();
        $token = $environment === null ? '' : ($this->vault->get(self::INTEGRATION, $environment)['auth_token'] ?? '');

        return $token;
    }

    private function mode(): IntegrationMode
    {
        $definition = $this->catalogue->find(self::INTEGRATION);

        return $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
    }
}
