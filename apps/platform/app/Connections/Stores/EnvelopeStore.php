<?php

declare(strict_types=1);

namespace App\Connections\Stores;

use App\Connections\StreamStore;
use App\Deals\Signatures;
use App\Models\Connection;
use App\Models\SignatureRequest;

/**
 * DocuSign's envelopes stream (B9.3, ADR 0038): only envelopes REOS
 * prepared from a deal are followed; the agent's other envelopes are
 * passed over and never stored.
 */
final readonly class EnvelopeStore implements StreamStore
{
    public function __construct(private Signatures $signatures) {}

    public function upsert(Connection $connection, array $records): int
    {
        $written = 0;

        foreach ($records as $record) {
            $id = $record['external_id'] ?? null;
            $status = $record['status'] ?? null;

            if (! is_string($id) || ! is_string($status)) {
                continue;
            }

            $request = SignatureRequest::query()->where('connection_id', $connection->id)->where('envelope_id', $id)->first();

            if ($request === null) {
                continue;
            }

            $this->signatures->apply($request, $status, self::signers($record['signers'] ?? null));
            $written++;
        }

        return $written;
    }

    public function remove(Connection $connection, array $externalIds): int
    {
        return 0;
    }

    /** Disconnecting keeps the deal's history and its filed copies. */
    public function forget(Connection $connection): void {}

    public function count(Connection $connection): int
    {
        return SignatureRequest::query()->where('connection_id', $connection->id)->count();
    }

    /** @return list<array{email: string, name: string, routing_order: int, status: string, at: string|null}> */
    private static function signers(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $s) {
            if (is_array($s) && is_string($s['email'] ?? null) && is_string($s['status'] ?? null)) {
                $out[] = ['email' => $s['email'], 'name' => is_string($s['name'] ?? null) ? $s['name'] : $s['email'], 'routing_order' => is_int($s['routing_order'] ?? null) ? $s['routing_order'] : 1, 'status' => $s['status'], 'at' => is_string($s['at'] ?? null) ? $s['at'] : null];
            }
        }

        return $out;
    }
}
