<?php

declare(strict_types=1);

namespace App\Connections;

use App\Models\Connection;

/**
 * An e-signature tool (DocuSign, B9.3, ADR 0038): REOS prepares a draft
 * envelope, the agent places the fields and presses Send in the tool's own
 * sender view, and REOS follows each signer and files the signed copy.
 */
interface SendsForSignature
{
    /**
     * A draft envelope (nothing is sent), with the documents in order and
     * each signer, telling the tool to report changes to `$webhookUrl`.
     *
     * @param  list<array{name: string, contents: string}>  $documents  PDFs
     * @param  list<array{name: string, email: string, routing_order: int}>  $signers
     * @return string the envelope's id
     */
    public function draft(Connection $connection, string $accessToken, string $subject, array $documents, array $signers, ?string $webhookUrl): string;

    /** A one-time link to the tool's sender view, coming back to `$returnUrl`. */
    public function senderView(Connection $connection, string $accessToken, string $envelopeId, string $returnUrl): string;

    /**
     * The envelope's status and each signer's.
     *
     * @return array{status: string, signers: list<array{email: string, name: string, routing_order: int, status: string, at: string|null}>}
     */
    public function envelope(Connection $connection, string $accessToken, string $envelopeId): array;

    /**
     * A completed envelope's signed copy (all documents in one PDF) and its certificate of completion.
     *
     * @return array{signed: string, certificate: string}
     */
    public function signedCopy(Connection $connection, string $accessToken, string $envelopeId): array;
}
