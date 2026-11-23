<?php

declare(strict_types=1);

namespace App\Connections\Drivers\DocuSign;

use App\Connections\ConnectedAccount;
use App\Connections\ConnectorDriver;
use App\Connections\Drivers\FollowUpBoss\WebhookTarget;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\SendsForSignature;
use App\Connections\SimulatorSignature;
use App\Connections\Stores\EnvelopeStore;
use App\Connections\SyncPage;
use App\Connections\TokenSet;
use App\Connections\WebhookDelivery;
use App\Models\Connection;
use App\Models\SimulatorRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * DocuSign on demo data (B9.3). The demo sign-in connects at once; the
 * sender view is a REOS page that looks after "Send"; and each sync moves
 * a sent envelope along (the next signer opens it, then signs), so a demo
 * reaches "completed" after a few syncs. Signed copies are small PDFs.
 */
final class DocuSignSimulator implements ConnectorDriver, SendsForSignature
{
    private const CONTAINER = 'ds';

    public function integration(): string
    {
        return DocuSignDriver::INTEGRATION;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri): string
    {
        return route('connections.simulator', ['tool' => $this->integration(), 'state' => $state, 'code_challenge' => $codeChallenge, 'redirect_uri' => $redirectUri]);
    }

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenSet
    {
        $claims = SimulatorSignature::open($code);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        if ($claims === null || ! is_string($claims['challenge'] ?? null) || ! hash_equals($claims['challenge'], $challenge) || ($claims['redirect_uri'] ?? null) !== $redirectUri) {
            throw new AccessRevoked('Simulator refused the code.');
        }

        return $this->tokens();
    }

    public function refresh(string $refreshToken): TokenSet
    {
        return SimulatorSignature::open($refreshToken) === null ? throw new AccessRevoked('Simulator refused the refresh token.') : $this->tokens();
    }

    public function revoke(string $token): void {}

    public function account(TokenSet $tokens): ConnectedAccount
    {
        return new ConnectedAccount('ds-sim-1', 'agent@docusign.example (demo)');
    }

    public function streams(): array
    {
        return ['envelopes' => EnvelopeStore::class];
    }

    public function fetch(Connection $connection, string $accessToken, string $stream, ?string $cursor, ?CarbonImmutable $since): SyncPage
    {
        if (($connection->meta['simulator_revoked'] ?? false) === true) {
            throw new AccessRevoked('Simulator revoked the connection.');
        }

        $records = [];
        foreach (self::rows($connection) as $row) {
            if (in_array($row->data['status'] ?? null, ['sent', 'delivered'], true)) {
                self::advance($row);
            }
            $records[] = EnvelopeRecords::envelope(self::payload($row));
        }

        return new SyncPage($records, [], null, true);
    }

    public function requestsPerMinute(): int
    {
        return 600;
    }

    public function verifyWebhook(Request $request): ?WebhookDelivery
    {
        $connection = WebhookTarget::open($request->query('c'), DocuSignDriver::PURPOSE);
        $payload = (array) json_decode($request->getContent(), true);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $envelope = $data['envelopeId'] ?? null;
        $event = $payload['event'] ?? null;

        return $connection !== null && is_string($envelope) && is_string($event) ? new WebhookDelivery('docusign:'.$envelope.':'.$event, [$connection]) : null;
    }

    public function draft(Connection $connection, string $accessToken, string $subject, array $documents, array $signers, ?string $webhookUrl): string
    {
        $id = (string) Str::uuid();
        SimulatorRecord::query()->create([
            'connection_id' => $connection->id, 'stream' => 'envelopes', 'container' => self::CONTAINER, 'external_id' => $id, 'etag' => substr(sha1($id), 0, 12), 'deleted' => false,
            'data' => [
                'status' => 'created', 'emailSubject' => $subject, 'documents' => array_map(static fn (array $d): array => ['name' => $d['name'], 'bytes' => strlen($d['contents'])], $documents),
                'signers' => array_map(static fn (array $s): array => ['email' => $s['email'], 'name' => $s['name'], 'routingOrder' => $s['routing_order'], 'status' => 'created'], $signers),
            ],
        ]);

        return $id;
    }

    public function senderView(Connection $connection, string $accessToken, string $envelopeId, string $returnUrl): string
    {
        return URL::temporarySignedRoute('connections.simulator.docusign', now()->addMinutes(10), ['connection' => $connection->id, 'envelope' => $envelopeId, 'return' => $returnUrl]);
    }

    public function envelope(Connection $connection, string $accessToken, string $envelopeId): array
    {
        $row = self::row($connection, $envelopeId);
        $record = EnvelopeRecords::envelope($row === null ? ['envelopeId' => $envelopeId, 'status' => 'voided'] : self::payload($row));

        return ['status' => $record['status'], 'signers' => $record['signers']];
    }

    public function signedCopy(Connection $connection, string $accessToken, string $envelopeId): array
    {
        $subject = self::row($connection, $envelopeId)?->data['emailSubject'] ?? null;
        $subject = is_string($subject) ? $subject : 'Envelope';

        return ['signed' => self::pdf('Signed (demo): '.$subject), 'certificate' => self::pdf('Certificate of completion (demo): '.$subject)];
    }

    /** "Send" pressed in the demo sender view: the first signers get it. */
    public static function send(Connection $connection, string $envelopeId): void
    {
        $row = self::row($connection, $envelopeId);

        if ($row === null || ($row->data['status'] ?? null) !== 'created') {
            return;
        }

        $signers = self::signers($row);
        $first = min(array_map(static fn (array $s): int => (int) $s['routingOrder'], $signers) ?: [1]);
        $row->forceFill(['data' => [...$row->data, 'status' => 'sent', 'signers' => array_map(static fn (array $s): array => [...$s, 'status' => (int) $s['routingOrder'] === $first ? 'sent' : 'created', 'sentDateTime' => (int) $s['routingOrder'] === $first ? CarbonImmutable::now()->toIso8601ZuluString() : null], $signers)]])->save();
    }

    /** Everyone signs at once (tests and demos). */
    public static function signAll(Connection $connection, string $envelopeId): void
    {
        $row = self::row($connection, $envelopeId);

        if ($row !== null) {
            $now = CarbonImmutable::now()->toIso8601ZuluString();
            $row->forceFill(['data' => [...$row->data, 'status' => 'completed', 'signers' => array_map(static fn (array $s): array => [...$s, 'status' => 'completed', 'signedDateTime' => $now], self::signers($row))]])->save();
        }
    }

    /** A signer turns it down (tests and demos). */
    public static function decline(Connection $connection, string $envelopeId, string $email): void
    {
        $row = self::row($connection, $envelopeId);

        if ($row !== null) {
            $row->forceFill(['data' => [...$row->data, 'status' => 'declined', 'signers' => array_map(static fn (array $s): array => $s['email'] === $email ? [...$s, 'status' => 'declined', 'declinedDateTime' => CarbonImmutable::now()->toIso8601ZuluString()] : $s, self::signers($row))]])->save();
        }
    }

    public static function row(Connection $connection, string $envelopeId): ?SimulatorRecord
    {
        return SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->where('external_id', $envelopeId)->first();
    }

    /** @return iterable<SimulatorRecord> */
    private static function rows(Connection $connection): iterable
    {
        return SimulatorRecord::query()->where('connection_id', $connection->id)->where('container', self::CONTAINER)->orderBy('id')->get();
    }

    /** One step: the current signer opens it, then signs; the next in order gets it; when all have signed, it's completed. */
    private static function advance(SimulatorRecord $row): void
    {
        $signers = self::signers($row);
        $now = CarbonImmutable::now()->toIso8601ZuluString();
        $current = array_find_key($signers, static fn (array $s): bool => in_array($s['status'], ['sent', 'delivered'], true));

        if ($current === null) {
            return;
        }

        if ($signers[$current]['status'] === 'sent') {
            $signers[$current] = [...$signers[$current], 'status' => 'delivered', 'deliveredDateTime' => $now];
            $row->forceFill(['data' => [...$row->data, 'status' => 'delivered', 'signers' => $signers]])->save();

            return;
        }

        $orders = [];
        foreach ($signers as $j => $o) {
            if ($j !== $current && $o['status'] === 'created') {
                $orders[] = (int) $o['routingOrder'];
            }
        }
        $next = $orders === [] ? null : min($orders);
        $signers = array_map(static fn (array $o, int $j): array => match (true) {
            $j === $current => [...$o, 'status' => 'completed', 'signedDateTime' => $now],
            $o['status'] === 'created' && (int) $o['routingOrder'] === $next => [...$o, 'status' => 'sent', 'sentDateTime' => $now],
            default => $o,
        }, $signers, array_keys($signers));
        $left = array_any($signers, static fn (array $o): bool => $o['status'] !== 'completed');

        $row->forceFill(['data' => [...$row->data, 'status' => $left ? 'sent' : 'completed', 'signers' => $signers]])->save();
    }

    /** @return list<array{email: string, name: string, routingOrder: int|string, status: string}&array<string, mixed>> */
    private static function signers(SimulatorRecord $row): array
    {
        $out = [];
        foreach (is_array($row->data['signers'] ?? null) ? $row->data['signers'] : [] as $s) {
            if (is_array($s) && is_string($s['email'] ?? null) && is_string($s['name'] ?? null) && is_string($s['status'] ?? null) && (is_int($s['routingOrder'] ?? null) || is_string($s['routingOrder'] ?? null))) {
                $out[] = $s;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> DocuSign's envelope shape */
    private static function payload(SimulatorRecord $row): array
    {
        return ['envelopeId' => $row->external_id, 'status' => $row->data['status'] ?? 'created', 'emailSubject' => $row->data['emailSubject'] ?? '', 'recipients' => ['signers' => self::signers($row)]];
    }

    /** A one-page PDF saying what it is. */
    public static function pdf(string $line): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($line));
        $stream = 'BT /F1 14 Tf 72 720 Td ('.$text.') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function tokens(): TokenSet
    {
        return new TokenSet(SimulatorSignature::seal(['account' => 'ds', 'kind' => 'access', 'nonce' => Str::random(8)]), SimulatorSignature::seal(['account' => 'ds', 'kind' => 'refresh']), CarbonImmutable::now()->addHours(8), ['signature']);
    }
}
