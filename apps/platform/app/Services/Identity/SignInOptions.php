<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Integrations\Catalogue;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;

/**
 * Which sign-in buttons to show: a provider appears when it runs on real
 * keys, or on demo data outside production. Never a button that can't work.
 */
final readonly class SignInOptions
{
    public function __construct(private Catalogue $catalogue, private IntegrationHub $hub) {}

    /** @return array{google: bool, apple: bool, phone: bool} */
    public function available(): array
    {
        return [
            'google' => $this->usable('google-sign-in'),
            'apple' => $this->usable('apple-sign-in'),
            'phone' => $this->usable('twilio-verify'),
        ];
    }

    /**
     * Whether a provider runs on demo data (outside production): the phone
     * app then signs in through the browser's persona picker, even for Apple.
     */
    public function demo(string $integration): bool
    {
        $definition = $this->catalogue->find($integration);

        return $definition !== null
            && ! app()->isProduction()
            && $this->hub->status($definition)->effectiveMode() === IntegrationMode::Simulator;
    }

    private function usable(string $integration): bool
    {
        $definition = $this->catalogue->find($integration);

        if ($definition === null) {
            return false;
        }

        $mode = $this->hub->status($definition)->effectiveMode();

        return $mode->environment() !== null || ($mode === IntegrationMode::Simulator && ! app()->isProduction());
    }
}
