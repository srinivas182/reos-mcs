<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Push;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Expo's push service with the access token from Settings › Integrations
 * (`expo-push`); Expo passes the message on to Apple (APNs) or Google (FCM).
 *
 * @phpstan-import-type PushMessage from PushSender
 * @phpstan-import-type PushTicket from PushSender
 */
final readonly class ExpoPushSender implements PushSender
{
    private const SEND = 'https://exp.host/--/api/v2/push/send';

    private const RECEIPTS = 'https://exp.host/--/api/v2/push/getReceipts';

    /** @param  array<string, string>  $keys  access_token (only when push security is on) */
    public function __construct(private Http $http, private array $keys) {}

    public function send(array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        $response = $this->request()->post(self::SEND, $messages);

        if (! $response->successful()) {
            throw new RuntimeException("Expo refused the push ({$response->status()})");
        }

        $tickets = [];

        foreach (array_values((array) $response->json('data', [])) as $ticket) {
            $ticket = is_array($ticket) ? $ticket : [];
            $status = is_string($ticket['status'] ?? null) ? $ticket['status'] : 'error';
            $id = $ticket['id'] ?? null;
            $error = is_array($ticket['details'] ?? null) ? ($ticket['details']['error'] ?? null) : null;

            $tickets[] = array_filter([
                'status' => $status,
                'id' => is_string($id) ? $id : null,
                'error' => is_string($error) ? $error : ($status === 'ok' ? null : 'Unknown'),
            ], static fn (?string $v): bool => $v !== null);
        }

        /** @var list<PushTicket> $tickets */
        return $tickets;
    }

    public function receipts(array $tickets): array
    {
        if ($tickets === []) {
            return [];
        }

        $response = $this->request()->post(self::RECEIPTS, ['ids' => $tickets]);

        if (! $response->successful()) {
            throw new RuntimeException("Expo refused the receipts request ({$response->status()})");
        }

        $receipts = [];

        foreach ((array) $response->json('data', []) as $id => $receipt) {
            if (! is_string($id) || ! is_array($receipt)) {
                continue;
            }

            $error = is_array($receipt['details'] ?? null) ? ($receipt['details']['error'] ?? null) : null;
            $receipts[$id] = ($receipt['status'] ?? null) === 'ok' ? null : (is_string($error) ? $error : 'Unknown');
        }

        return $receipts;
    }

    private function request(): PendingRequest
    {
        $request = $this->http->acceptJson()->asJson()->connectTimeout(5)->timeout(15);
        $token = $this->keys['access_token'] ?? '';

        return $token === '' ? $request : $request->withToken($token);
    }
}
