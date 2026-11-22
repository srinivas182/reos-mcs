<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Sms;

use App\Messaging\TextMessagesFactory;

/**
 * Replies texted to REOS's number (B6.2c): STOP and its synonyms withdraw
 * the person's SMS consent, START gives it again, HELP answers with who we
 * are and how to stop. Twilio signs each request with the auth token.
 */
final readonly class TwilioReplies
{
    /** Carrier and CTIA opt-out words. */
    public const STOP = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'REVOKE', 'OPTOUT'];

    public const START = ['START', 'UNSTOP', 'YES'];

    public const HELP = ['HELP', 'INFO'];

    public function __construct(private TextMessagesFactory $texts, private TextAlerts $alerts) {}

    /**
     * Twilio's signature: base64 HMAC-SHA1 of the full URL followed by each
     * POST field name and value, sorted by name, keyed with the auth token.
     *
     * @param  array<string, mixed>  $fields
     */
    public function signed(string $url, array $fields, ?string $signature): bool
    {
        $token = $this->texts->authToken();

        // The simulator (never production) takes unsigned test replies.
        if ($token === null) {
            return true;
        }

        if ($token === '' || $signature === null || $signature === '') {
            return false;
        }

        ksort($fields);
        $data = $url;

        foreach ($fields as $name => $value) {
            $data .= $name.(is_scalar($value) ? (string) $value : '');
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $data, $token, true)), $signature);
    }

    /** Acts on the reply; returns the text to send back, if any. */
    public function handle(string $from, string $body): ?string
    {
        // The first word, letters only: "Stop." and "stop please" both count.
        $first = preg_split('/\s+/', trim($body))[0] ?? '';
        $word = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $first));

        if (in_array($word, self::STOP, true)) {
            // Twilio's own opt-out handling confirms the STOP to the person.
            $this->alerts->stopByText($from);

            return null;
        }

        if (in_array($word, self::START, true)) {
            $this->alerts->startByText($from);

            return null;
        }

        if (! in_array($word, self::HELP, true)) {
            return null;
        }

        $help = __('notifications.texts.sms.help');

        return is_string($help) ? $help : null;
    }
}
