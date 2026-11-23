<?php

declare(strict_types=1);

namespace App\Content\Avatars;

use App\Integrations\Catalogue;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;

/**
 * The avatar vendor for a course (A7.3): the school's choice of HeyGen or
 * Synthesia on screen 43, live when that integration has sandbox or live
 * keys in Settings › Integrations; otherwise the simulator.
 */
final readonly class AvatarVideos
{
    public const VENDORS = ['heygen', 'synthesia'];

    public function __construct(
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    public function for(string $vendor): AvatarVideo
    {
        $definition = in_array($vendor, self::VENDORS, true) ? $this->catalogue->find($vendor) : null;
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
        $environment = $mode->environment();

        if ($environment === null) {
            return new SimulatedAvatarVideo;
        }

        $keys = array_filter($this->vault->get($vendor, $environment), is_string(...));

        if (($keys['api_key'] ?? '') === '') {
            return new SimulatedAvatarVideo;
        }

        return $vendor === 'heygen' ? new HeyGenVideo($keys) : new SynthesiaVideo($keys, $environment === CredentialEnvironment::Sandbox);
    }
}
