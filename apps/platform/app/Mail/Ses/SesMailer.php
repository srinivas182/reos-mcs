<?php

declare(strict_types=1);

namespace App\Mail\Ses;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Aws\SesV2\SesV2Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\SesV2Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * The `reos` mailer (B6.2d): Amazon SES with the keys and region from
 * Settings › Integrations (`amazon-ses`), resolved when mail is sent so a
 * change there needs no deploy. Until SES is set up, mail goes through the
 * fallback mailer (`MAIL_REOS_FALLBACK`, the log by default).
 */
final readonly class SesMailer
{
    public const INTEGRATION = 'amazon-ses';

    /** The mailer name in config/mail.php. */
    public const MAILER = 'reos';

    public function __construct(
        private Container $container,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
    ) {}

    public function transport(): TransportInterface
    {
        $keys = $this->keys();

        if ($keys === null) {
            $fallback = config('mail.mailers.reos.fallback');

            return $this->container->make(MailManager::class)->mailer(is_string($fallback) ? $fallback : 'log')->getSymfonyTransport();
        }

        return new SesV2Transport(new SesV2Client([
            'version' => 'latest',
            'region' => $keys['region'] ?? 'us-east-2',
            'credentials' => ['key' => $keys['access_key_id'] ?? '', 'secret' => $keys['secret_access_key'] ?? ''],
        ]));
    }

    /** The From address verified in SES, when SES is set up. */
    public function fromAddress(): ?string
    {
        $from = $this->keys()['from_email'] ?? '';

        return filter_var($from, FILTER_VALIDATE_EMAIL) === false ? null : $from;
    }

    /** The SNS topic SES reports bounces and complaints to, if pinned. */
    public function topicArn(): ?string
    {
        $arn = $this->keys()['sns_topic_arn'] ?? '';

        return $arn === '' ? null : $arn;
    }

    /** @return array<string, string>|null */
    private function keys(): ?array
    {
        $definition = $this->catalogue->find(self::INTEGRATION);
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
        $environment = $mode->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get(self::INTEGRATION, $environment);

        return ($keys['access_key_id'] ?? '') === '' || ($keys['secret_access_key'] ?? '') === '' ? null : $keys;
    }
}
