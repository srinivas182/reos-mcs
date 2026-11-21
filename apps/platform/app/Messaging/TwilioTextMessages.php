<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/** Twilio Programmable Messaging with the keys from Settings › Integrations (`twilio-sms`). */
final readonly class TwilioTextMessages implements TextMessages
{
    /** @param  array<string, string>  $keys  account_sid, auth_token, from_number */
    public function __construct(private Http $http, private array $keys) {}

    public function send(string $to, string $body): void
    {
        $sid = $this->keys['account_sid'] ?? '';
        $response = $this->http->asForm()->withBasicAuth($sid, $this->keys['auth_token'] ?? '')
            ->connectTimeout(5)->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $this->keys['from_number'] ?? '',
                'Body' => $body,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Twilio refused the text ({$response->status()})");
        }
    }
}
