<?php

declare(strict_types=1);

namespace App\Connections\Drivers\DocuSign;

/**
 * DocuSign's envelopes and recipients in REOS's words (B9.3, ADR 0038).
 * Envelope statuses: created (a draft), sent, delivered, completed,
 * declined, voided. A signer is waiting until their turn, then sent,
 * delivered (opened), signed or declined.
 */
final class EnvelopeRecords
{
    public const ENVELOPE = ['created' => 'draft', 'sent' => 'sent', 'delivered' => 'delivered', 'signed' => 'delivered', 'completed' => 'completed', 'declined' => 'declined', 'voided' => 'voided'];

    public const SIGNER = ['created' => 'waiting', 'sent' => 'sent', 'delivered' => 'delivered', 'signed' => 'signed', 'completed' => 'signed', 'declined' => 'declined', 'autoresponded' => 'declined'];

    /**
     * @param  array<array-key, mixed>  $envelope  with `recipients.signers` when asked for
     * @return array{external_id: string, status: string, signers: list<array{email: string, name: string, routing_order: int, status: string, at: string|null}>}
     */
    public static function envelope(array $envelope): array
    {
        $recipients = is_array($envelope['recipients'] ?? null) ? $envelope['recipients'] : [];

        return [
            'external_id' => self::text($envelope['envelopeId'] ?? null) ?? '',
            'status' => self::ENVELOPE[self::text($envelope['status'] ?? null) ?? ''] ?? 'sent',
            'signers' => self::signers($recipients),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $recipients  `{signers: [...]}`
     * @return list<array{email: string, name: string, routing_order: int, status: string, at: string|null}>
     */
    public static function signers(array $recipients): array
    {
        $out = [];

        foreach (is_array($recipients['signers'] ?? null) ? $recipients['signers'] : [] as $signer) {
            if (! is_array($signer)) {
                continue;
            }
            $email = self::text($signer['email'] ?? null);
            if ($email === null) {
                continue;
            }
            $status = self::SIGNER[self::text($signer['status'] ?? null) ?? ''] ?? 'waiting';
            $at = match ($status) {
                'signed' => $signer['signedDateTime'] ?? null,
                'declined' => $signer['declinedDateTime'] ?? null,
                'delivered' => $signer['deliveredDateTime'] ?? null,
                'sent' => $signer['sentDateTime'] ?? null,
                default => null,
            };
            $order = $signer['routingOrder'] ?? 1;
            $out[] = ['email' => $email, 'name' => self::text($signer['name'] ?? null) ?? $email, 'routing_order' => is_numeric($order) ? (int) $order : 1, 'status' => $status, 'at' => self::text($at)];
        }

        return $out;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
