<?php

declare(strict_types=1);

namespace App\Mail\Ses;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Bounces and complaints from Amazon SES, delivered by Amazon SNS (B6.2d).
 * Every message is checked against SNS's signature (the signing
 * certificate must come from an sns.*.amazonaws.com address) and, when one
 * is pinned in Settings › Integrations, the topic. A subscription is
 * confirmed by visiting its SubscribeURL; a permanent bounce or a complaint
 * suppresses the address. Transient bounces (a full mailbox) are ignored.
 */
final readonly class SesNotifications
{
    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    /** SNS hosts its certificates and subscription links only here. */
    private const SNS_HOST = '/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/';

    public function __construct(private Http $http, private Cache $cache, private SesMailer $ses, private EmailSuppressions $suppressions) {}

    public function receive(string $body): string
    {
        $message = json_decode($body, true);

        if (! is_array($message) || ! $this->verified($message)) {
            return self::REJECTED;
        }

        $pinned = $this->ses->topicArn();

        if ($pinned !== null && ($message['TopicArn'] ?? null) !== $pinned) {
            return self::REJECTED;
        }

        return match ($message['Type'] ?? null) {
            'SubscriptionConfirmation' => $this->confirm($message),
            'Notification' => $this->notification($message),
            default => self::ACCEPTED,
        };
    }

    /** @param  array<mixed>  $message */
    private function verified(array $message): bool
    {
        $certificate = $this->certificate($message['SigningCertURL'] ?? null);
        $signature = base64_decode(is_string($message['Signature'] ?? null) ? $message['Signature'] : '', true);
        $algorithm = match ($message['SignatureVersion'] ?? null) {
            '1' => OPENSSL_ALGO_SHA1,
            '2' => OPENSSL_ALGO_SHA256,
            default => null,
        };

        if ($certificate === null || $signature === false || $signature === '' || $algorithm === null) {
            return false;
        }

        $fields = ($message['Type'] ?? null) === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $signed = '';

        foreach ($fields as $field) {
            if (isset($message[$field]) && is_string($message[$field])) {
                $signed .= $field."\n".$message[$field]."\n";
            }
        }

        return openssl_verify($signed, $signature, $certificate, $algorithm) === 1;
    }

    private function certificate(mixed $url): ?string
    {
        if (! is_string($url) || ! $this->snsUrl($url) || ! str_ends_with((string) parse_url($url, PHP_URL_PATH), '.pem')) {
            return null;
        }

        $pem = $this->cache->remember('reos:sns-cert:'.hash('sha256', $url), 86_400, function () use ($url): string {
            try {
                $response = $this->http->connectTimeout(5)->timeout(10)->get($url);

                return $response->successful() ? $response->body() : '';
            } catch (Throwable) {
                return '';
            }
        });

        return str_contains($pem, 'BEGIN CERTIFICATE') ? $pem : null;
    }

    private function snsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && preg_match(self::SNS_HOST, $parts['host'] ?? '') === 1;
    }

    /** @param  array<mixed>  $message */
    private function confirm(array $message): string
    {
        $url = $message['SubscribeURL'] ?? null;

        if (! is_string($url) || ! $this->snsUrl($url)) {
            return self::REJECTED;
        }

        $this->http->connectTimeout(5)->timeout(10)->get($url);

        return self::ACCEPTED;
    }

    /** @param  array<mixed>  $message */
    private function notification(array $message): string
    {
        $event = json_decode(is_string($message['Message'] ?? null) ? $message['Message'] : '', true);

        if (! is_array($event)) {
            return self::ACCEPTED;
        }

        // SES notifications say notificationType; event publishing says eventType.
        $type = $event['notificationType'] ?? $event['eventType'] ?? null;

        if ($type === 'Bounce' && is_array($event['bounce'] ?? null) && ($event['bounce']['bounceType'] ?? null) === 'Permanent') {
            foreach ($this->recipients($event['bounce']['bouncedRecipients'] ?? null) as [$email, $detail]) {
                $this->suppressions->suppress($email, EmailSuppressions::BOUNCE, $detail);
            }
        }

        if ($type === 'Complaint' && is_array($event['complaint'] ?? null)) {
            $feedback = $event['complaint']['complaintFeedbackType'] ?? null;

            foreach ($this->recipients($event['complaint']['complainedRecipients'] ?? null) as [$email]) {
                $this->suppressions->suppress($email, EmailSuppressions::COMPLAINT, is_string($feedback) ? $feedback : null);
            }
        }

        return self::ACCEPTED;
    }

    /** @return list<array{0: string, 1: string|null}> */
    private function recipients(mixed $list): array
    {
        $found = [];

        foreach (is_array($list) ? $list : [] as $recipient) {
            $email = is_array($recipient) ? ($recipient['emailAddress'] ?? null) : null;
            $detail = is_array($recipient) ? ($recipient['diagnosticCode'] ?? null) : null;

            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $found[] = [$email, is_string($detail) ? $detail : null];
            }
        }

        return $found;
    }
}
