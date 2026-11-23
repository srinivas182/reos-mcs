<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Deals;

use App\Deals\DealAccess;
use App\Deals\DealFiles;
use App\Deals\Signatures;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\DealFileRequest;
use App\Http\Requests\Deals\SendForSignatureRequest;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Signatures from a deal (B9.3, ADR 0038), through the agent's own
 * DocuSign: REOS prepares the envelope; the agent places the fields and
 * presses Send in DocuSign.
 */
final class SignatureController extends Controller
{
    /**
     * Add a PDF to a deal.
     *
     * Kept in REOS (never sent to the transaction tool), to send for signature. It's virus-scanned first: `scan` is `pending` until then, and `url` is null.
     *
     * @status 201
     *
     * @response array{data: array{uuid: string, kind: 'upload'|'signed'|'certificate', name: string, size: int, added_at: string|null, scan: 'pending'|'clean'|'infected', url: string|null}}
     */
    public function upload(string $deal, DealFileRequest $request, #[CurrentUser] User $user, DealAccess $access): JsonResponse
    {
        $file = DealFiles::upload($access->find($user, $deal), $request->upload());

        return response()->json(['data' => DealFiles::row($file)], 201);
    }

    /**
     * Prepare an envelope.
     *
     * A draft in the agent's DocuSign with the chosen files and signers, and the link to DocuSign's sender view, where the agent places the fields and presses Send. Nothing is sent until then.
     *
     * @status 201
     *
     * @response array{data: array{uuid: string, subject: string, status: string, documents: list<string>, signers: list<array{name: string, email: string, routing_order: int, status: string, at: string|null}>, sent_at: string|null, completed_at: string|null, filed_in: string|null}, sender_url: string}
     */
    public function store(string $deal, SendForSignatureRequest $request, #[CurrentUser] User $user, DealAccess $access, Signatures $signatures): JsonResponse
    {
        $started = $signatures->start($user, $access->find($user, $deal), $request->values());

        return response()->json(['data' => Signatures::row($started['request']), 'sender_url' => $started['sender_url']], 201);
    }

    /**
     * Continue a draft in DocuSign.
     *
     * A new link to DocuSign's sender view for an envelope not sent yet.
     *
     * @response array{sender_url: string}
     */
    public function sender(SignatureRequest $signature, #[CurrentUser] User $user, DealAccess $access, Signatures $signatures): JsonResponse
    {
        $access->allow($user);
        abort_unless($signature->user_id === $user->id, 404);

        return response()->json(['sender_url' => $signatures->senderUrl($signature)]);
    }
}
