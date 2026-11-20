<?php

declare(strict_types=1);

namespace App\Identity\Phone;

use App\Identity\SignInFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Twilio Verify: Twilio sends the code (in the person's language) and checks
 * it, with its fraud protection against SMS pumping.
 */
final readonly class TwilioVerifyPhoneVerifier implements PhoneVerifier
{
    /** @param  array<string, string>  $credentials */
    public function __construct(private Http $http, private array $credentials) {}

    public function send(string $phone, string $locale): void
    {
        try {
            $response = $this->request()->post($this->url('Verifications'), [
                'To' => $phone,
                'Channel' => 'sms',
                'Locale' => $locale,
            ]);
        } catch (ConnectionException) {
            throw new SignInFailed('identity.errors.codeNotSent', 'Twilio unreachable', status: 503);
        }

        if (! $response->successful()) {
            throw new SignInFailed('identity.errors.codeNotSent', "Twilio Verify answered {$response->status()}", status: 503);
        }
    }

    public function check(string $phone, string $code): bool
    {
        try {
            $response = $this->request()->post($this->url('VerificationCheck'), ['To' => $phone, 'Code' => $code]);
        } catch (ConnectionException) {
            throw new SignInFailed('identity.errors.codeNotChecked', 'Twilio unreachable', status: 503);
        }

        // Twilio answers 404 once a code has expired or been used.
        return $response->successful() && $response->json('status') === 'approved';
    }

    private function request(): PendingRequest
    {
        return $this->http->asForm()->connectTimeout(5)->timeout(10)
            ->withBasicAuth($this->credentials['account_sid'] ?? '', $this->credentials['auth_token'] ?? '');
    }

    private function url(string $path): string
    {
        $service = $this->credentials['verify_service_sid'] ?? '';

        return "https://verify.twilio.com/v2/Services/{$service}/{$path}";
    }
}
