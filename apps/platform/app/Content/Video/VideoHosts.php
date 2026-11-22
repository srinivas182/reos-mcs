<?php

declare(strict_types=1);

namespace App\Content\Video;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;

/**
 * Picks the video host from how Mux runs in Settings › Integrations: test or
 * live keys use Mux; the Simulator (outside production) pretends; Off (or
 * keys missing) plays the original file from storage.
 */
final readonly class VideoHosts
{
    public const INTEGRATION = 'mux';

    public function __construct(
        private Application $app,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private Http $http,
    ) {}

    public function host(): ?VideoHost
    {
        $mode = $this->mode();

        if ($mode === IntegrationMode::Simulator) {
            return $this->app->isProduction() ? null : new SimulatedVideoHost;
        }

        $keys = $this->keys();

        return $keys === null ? null : new MuxVideoHost($this->http, $keys);
    }

    /** Mux itself (test or live keys), for webhooks and captions. */
    public function mux(): ?MuxVideoHost
    {
        $keys = $this->keys();

        return $keys === null ? null : new MuxVideoHost($this->http, $keys);
    }

    public function keys(): ?MuxKeys
    {
        $environment = $this->mode()->environment();

        if ($environment === null) {
            return null;
        }

        $values = $this->vault->get(self::INTEGRATION, $environment);
        $value = static fn (string $key): string => is_string($values[$key] ?? null) ? trim($values[$key]) : '';

        if ($value('access_token_id') === '' || $value('secret_key') === '') {
            return null;
        }

        return new MuxKeys($value('access_token_id'), $value('secret_key'), $value('webhook_secret'), $value('key_id'), $value('private_key'));
    }

    private function mode(): IntegrationMode
    {
        $definition = $this->catalogue->find(self::INTEGRATION);

        return $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
    }
}
