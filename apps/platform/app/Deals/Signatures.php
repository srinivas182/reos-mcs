<?php

declare(strict_types=1);

namespace App\Deals;

use App\Connections\AcceptsDocuments;
use App\Connections\ConnectionStatus;
use App\Connections\ConnectorRegistry;
use App\Connections\Drivers\DocuSign\DocuSignDriver;
use App\Connections\SendsForSignature;
use App\Models\Connection;
use App\Models\Deal;
use App\Models\DealFile;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Notifications\SignatureCompleted;
use App\Services\Connections\ConnectionTokens;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Signatures from a deal (B9.3, ADR 0038), through the agent's own DocuSign.
 *
 * - **Send:** REOS makes a draft envelope with the chosen PDFs and the
 *   signers, then opens DocuSign's sender view, where the agent places the
 *   fields and presses Send. Nothing is sent from REOS itself.
 * - **Follow:** DocuSign Connect and the 15-minute poll both lead to
 *   `apply`, which records the envelope's and each signer's status.
 * - **File:** once everyone has signed, the signed copy and the certificate
 *   of completion are filed on the deal and, when the deal's transaction
 *   tool takes documents (dotloop, SkySlope), uploaded there too; then
 *   the agent gets a push.
 *
 * @phpstan-type Signer array{name: string, email: string, routing_order: int, status: string, at: string|null}
 * @phpstan-type RequestRow array{uuid: string, subject: string, status: string, documents: list<string>, signers: list<Signer>, sent_at: string|null, completed_at: string|null, filed_in: string|null}
 */
final readonly class Signatures
{
    public function __construct(private ConnectorRegistry $registry, private ConnectionTokens $tokens, private Tenancy $tenancy) {}

    /** The agent's DocuSign, when it's connected and working. */
    public function connection(User $user): ?Connection
    {
        if (! $this->registry->supports(DocuSignDriver::INTEGRATION)) {
            return null;
        }

        return Connection::query()->where('integration_id', DocuSignDriver::INTEGRATION)->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)
            ->where('status', ConnectionStatus::Active->value)->latest('id')->first();
    }

    /**
     * A draft envelope for the deal, and the link to DocuSign's sender view.
     *
     * @param  array{subject: string, files: list<string>, signers: list<array{name: string, email: string, routing_order: int}>}  $input
     * @return array{request: SignatureRequest, sender_url: string}
     *
     * @throws ValidationException
     */
    public function start(User $user, Deal $deal, array $input): array
    {
        $connection = $this->connection($user) ?? throw ValidationException::withMessages(['connection' => __('deals.signatures.errors.connect')]);
        $files = DealFile::query()->where('deal_id', $deal->id)->whereIn('uuid', $input['files'])->whereIn('kind', ['upload', 'signed'])->where('scan_status', 'clean')->get()
            ->sortBy(static fn (DealFile $f): int|false => array_search($f->uuid, $input['files'], true))->values();

        if ($files->count() !== count(array_unique($input['files']))) {
            throw ValidationException::withMessages(['files' => __('deals.signatures.errors.files')]);
        }

        [$driver, $token] = $this->driver($connection);
        $documents = array_values($files->map(static fn (DealFile $f): array => ['name' => $f->name, 'contents' => DealFiles::contents($f)])->all());
        $webhook = url('webhooks/'.DocuSignDriver::INTEGRATION);
        $envelope = $driver->draft($connection, $token, $input['subject'], $documents, $input['signers'], str_starts_with($webhook, 'https://') ? $webhook : null);

        $request = SignatureRequest::query()->create([
            'uuid' => (string) Str::uuid(), 'deal_id' => $deal->id, 'user_id' => $user->id, 'organisation_id' => null, 'connection_id' => $connection->id,
            'envelope_id' => $envelope, 'subject' => $input['subject'], 'status' => 'draft',
            'signers' => array_map(static fn (array $s): array => [...$s, 'status' => 'waiting', 'at' => null], $input['signers']),
            'documents' => array_values($files->pluck('name')->all()),
        ]);

        return ['request' => $request, 'sender_url' => $this->senderUrl($request)];
    }

    /** DocuSign's sender view again, for a draft the agent left without sending. */
    public function senderUrl(SignatureRequest $request): string
    {
        if ($request->status !== 'draft' || $request->connection === null || $request->envelope_id === null) {
            throw ValidationException::withMessages(['request' => __('deals.signatures.errors.notDraft')]);
        }

        [$driver, $token] = $this->driver($request->connection);

        return $driver->senderView($request->connection, $token, $request->envelope_id, route('deals.signatures.returned', $request->uuid));
    }

    /** Back from the sender view: what DocuSign now says about the envelope. */
    public function refresh(SignatureRequest $request): SignatureRequest
    {
        if ($request->connection === null || $request->envelope_id === null || in_array($request->status, SignatureRequest::FINAL, true) && $request->filed_at !== null) {
            return $request;
        }

        [$driver, $token] = $this->driver($request->connection);
        $now = $driver->envelope($request->connection, $token, $request->envelope_id);

        return $this->apply($request, $now['status'], $now['signers']);
    }

    /**
     * The envelope's status and its signers' from DocuSign. A completed
     * envelope is filed once.
     *
     * @param  list<array{email: string, name: string, routing_order: int, status: string, at: string|null}>  $signers
     */
    public function apply(SignatureRequest $request, string $status, array $signers): SignatureRequest
    {
        $status = in_array($status, SignatureRequest::STATUSES, true) ? $status : $request->status;
        $byEmail = [];
        foreach ($signers as $signer) {
            $byEmail[mb_strtolower($signer['email']).'|'.$signer['routing_order']] = $signer;
        }

        $request->forceFill([
            'status' => $status,
            'signers' => array_map(static function (array $s) use ($byEmail): array {
                $now = $byEmail[mb_strtolower($s['email']).'|'.$s['routing_order']] ?? null;

                return $now === null ? $s : [...$s, 'status' => $now['status'], 'at' => $now['at']];
            }, $request->signers),
            'sent_at' => $status !== 'draft' ? ($request->sent_at ?? CarbonImmutable::now()) : null,
            'completed_at' => $status === 'completed' ? ($request->completed_at ?? CarbonImmutable::now()) : $request->completed_at,
        ])->save();

        if ($status === 'completed' && $request->filed_at === null) {
            $this->file($request);
        }

        return $request;
    }

    /**
     * The signed copy and certificate onto the deal, then into the deal's
     * tool when it takes documents, then the push. Run as the platform:
     * the deal may belong to a brokerage's tool, and everything here is
     * the agent's own envelope.
     */
    private function file(SignatureRequest $request): void
    {
        $this->tenancy->asPlatform(function () use ($request): void {
            $deal = $request->deal;
            $connection = $request->connection;

            if ($connection === null || $request->envelope_id === null) {
                return;
            }

            [$driver, $token] = $this->driver($connection);
            $copy = $driver->signedCopy($connection, $token, $request->envelope_id);
            $name = Str::limit($request->subject, 150, '');
            DealFiles::put($deal, 'signed', __('deals.signatures.signedName', ['subject' => $name]), $copy['signed'], $request->id);
            DealFiles::put($deal, 'certificate', __('deals.signatures.certificateName', ['subject' => $name]), $copy['certificate'], $request->id);
            $request->forceFill(['filed_at' => CarbonImmutable::now(), 'filed_in' => $this->fileInTool($deal, __('deals.signatures.signedName', ['subject' => $name]).'.pdf', $copy['signed'])])->save();

            $deal->user->notify(new SignatureCompleted($deal->uuid, $request->subject, $deal->property));
        });
    }

    /** The deal's tool, when it takes documents and is connected: its id once the copy is there. */
    private function fileInTool(Deal $deal, string $name, string $contents): ?string
    {
        $tool = $deal->connection_id === null ? null : Connection::query()->find($deal->connection_id);

        if ($tool === null || ! $tool->status->syncs() || ! $this->registry->supports($tool->integration_id)) {
            return null;
        }

        try {
            $driver = $this->registry->driver($tool->integration_id, $tool->mode);
            if (! $driver instanceof AcceptsDocuments) {
                return null;
            }

            return $driver->uploadDocument($tool, $this->tokens->accessToken($tool, $driver), $deal, $name, $contents) ? $tool->integration_id : null;
        } catch (Throwable $e) {
            // The copy is safe on the deal; the tool can be tried again by hand.
            report($e);

            return null;
        }
    }

    /** @return array{0: SendsForSignature, 1: string} */
    private function driver(Connection $connection): array
    {
        $driver = $this->registry->driver($connection->integration_id, $connection->mode);

        if (! $driver instanceof SendsForSignature) {
            throw ValidationException::withMessages(['connection' => __('deals.signatures.errors.connect')]);
        }

        return [$driver, $this->tokens->accessToken($connection, $driver)];
    }

    /** @return RequestRow */
    public static function row(SignatureRequest $request): array
    {
        return [
            'uuid' => $request->uuid, 'subject' => $request->subject, 'status' => $request->status, 'documents' => $request->documents, 'signers' => $request->signers,
            'sent_at' => $request->sent_at?->toIso8601String(), 'completed_at' => $request->completed_at?->toIso8601String(), 'filed_in' => $request->filed_in,
        ];
    }
}
