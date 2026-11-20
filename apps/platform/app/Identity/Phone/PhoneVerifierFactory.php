<?php

declare(strict_types=1);

namespace App\Identity\Phone;

use App\Identity\SignInFailed;
use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;

/** Picks how codes are sent from how Twilio Verify runs in Settings › Integrations. */
final readonly class PhoneVerifierFactory
{
    public const INTEGRATION = 'twilio-verify';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    public function make(): PhoneVerifier
    {
        $definition = $this->catalogue->find(self::INTEGRATION);
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();

        if ($mode === IntegrationMode::Simulator && ! $this->container->make('app')->isProduction()) {
            return $this->container->make(SimulatorPhoneVerifier::class);
        }

        $environment = $mode->environment();

        if ($environment === null) {
            throw SignInFailed::unavailable();
        }

        return new TwilioVerifyPhoneVerifier(
            $this->container->make(Http::class),
            $this->vault->get(self::INTEGRATION, $environment),
        );
    }
}
