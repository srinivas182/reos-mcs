<?php

declare(strict_types=1);

namespace App\Deals;

use App\Compliance\ComplianceEngine;
use App\Models\AgentPage;
use App\Models\ClientPortal;
use App\Models\ClientReview;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Notifications\ClientPortalLink;
use App\Services\Audit\AuditLogger;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The client portal (B14.2, ADR 0065): one private link per client on a
 * deal. No account or password: the link is the key, so it is long,
 * random, kept only as a hash for lookups, expires, and can be turned off.
 *
 * The client sees their agent (name, brokerage, contact details from the
 * agent's card), the deal's stage and key dates, the documents the
 * transaction tool tracks, the documents waiting for their signature
 * (signed right there through DocuSign's embedded signing), and — once the
 * deal has closed — a review request. A review the client allows shows on
 * the agent's public page as "Rosa L.".
 *
 * Nothing about other clients or the agent's other deals is reachable.
 *
 * @phpstan-type PortalRow array{id: string, name: string, email: string|null, url: string, opened_at: string|null, sent_at: string|null, expires_at: string, review: array{rating: int, publish: bool}|null}
 * @phpstan-type PortalView array{client: string, language: 'en'|'es', agent: array{name: string, brokerage: string|null, phone: string|null, email: string|null, page_url: string|null, card_url: string|null}, deal: array{property: string, city: string|null, side: string, stage: string, price: int|null}, dates: list<array{kind: string, label: string|null, due_on: string, done: bool}>, documents: list<array{name: string, status: string}>, signatures: list<array{id: string, subject: string, status: string, mine: string|null, can_sign: bool, in_portal: bool}>, review: array{asked: bool, mine: array{rating: int, comment: string|null, publish: bool}|null}}
 */
final readonly class ClientPortals
{
    /** A link lasts this long, renewed when the agent shares it again. */
    public const DAYS = 180;

    public function __construct(private DealAccess $access, private Signatures $signatures, private Tenancy $tenancy, private ComplianceEngine $compliance, private AuditLogger $audit) {}

    /**
     * The deal's client links.
     *
     * @return list<PortalRow>
     */
    public function list(User $agent, string $dealUuid): array
    {
        $deal = $this->access->find($agent, $dealUuid);

        return array_values(array_map(fn (ClientPortal $p): array => $this->row($p), ClientPortal::query()->where('deal_id', $deal->id)->whereNull('revoked_at')->orderBy('id')->get()->all()));
    }

    /**
     * A link for a client on the deal; the same client's open link is
     * reused (and renewed).
     *
     * @return PortalRow
     */
    public function create(User $agent, string $dealUuid, string $name, ?string $email, string $language): array
    {
        $deal = $this->access->find($agent, $dealUuid);
        $email = $email === null || trim($email) === '' ? null : mb_strtolower(trim($email));
        $existing = $email === null ? null : ClientPortal::query()->where('deal_id', $deal->id)->where('email', $email)->whereNull('revoked_at')->first();

        if ($existing instanceof ClientPortal) {
            $existing->forceFill(['name' => trim($name), 'language' => $language, 'expires_at' => now()->addDays(self::DAYS)])->save();

            return $this->row($existing);
        }

        $token = Str::random(48);
        $portal = ClientPortal::query()->create([
            'uuid' => (string) Str::uuid7(),
            'deal_id' => $deal->id,
            'organisation_id' => $deal->organisation_id,
            'user_id' => $agent->id,
            'name' => trim($name),
            'email' => $email,
            'language' => $language === 'es' ? 'es' : 'en',
            'token_hash' => hash('sha256', $token),
            'token' => $token,
            'expires_at' => now()->addDays(self::DAYS),
        ]);
        $this->audit->record('portal.created', 'deal', $deal->uuid, ['portal' => $portal->uuid], $agent, $deal->organisation_id);

        return $this->row($portal);
    }

    /**
     * Emails the link to the client, in their language.
     *
     * @return PortalRow
     */
    public function send(User $agent, string $portalUuid): array
    {
        $portal = $this->mine($agent, $portalUuid);

        if ($portal->email === null) {
            throw ValidationException::withMessages(['email' => __('portal.errors.noEmail')]);
        }

        Notification::route('mail', $portal->email)->notify(new ClientPortalLink($portal, $this->url($portal), (string) $agent->name));
        $portal->forceFill(['sent_at' => now(), 'expires_at' => now()->addDays(self::DAYS)])->save();

        return $this->row($portal);
    }

    /** Turns the link off at once. */
    public function revoke(User $agent, string $portalUuid): void
    {
        $portal = $this->mine($agent, $portalUuid);
        $portal->forceFill(['revoked_at' => now()])->save();
        $this->audit->record('portal.revoked', 'deal', $portal->deal->uuid, ['portal' => $portal->uuid], $agent, $portal->organisation_id);
    }

    /** The open portal behind a link, or null. */
    public function find(string $token): ?ClientPortal
    {
        if (preg_match('/^[A-Za-z0-9]{48}$/', $token) !== 1) {
            return null;
        }

        $portal = $this->tenancy->asPlatform(static fn (): ?ClientPortal => ClientPortal::query()->where('token_hash', hash('sha256', $token))->first());

        return $portal instanceof ClientPortal && $portal->open() ? $portal : null;
    }

    /**
     * What the client sees. Opening it notes the first visit.
     *
     * @return PortalView
     */
    public function view(ClientPortal $portal, ?string $returnedFrom = null): array
    {
        return $this->tenancy->run($portal->organisation_id, function () use ($portal, $returnedFrom): array {
            $deal = Deal::query()->with(['dates', 'user'])->findOrFail($portal->deal_id);

            if ($portal->opened_at === null) {
                $portal->forceFill(['opened_at' => now()])->save();
            }

            // Back from signing: what DocuSign now says.
            if ($returnedFrom !== null) {
                $request = SignatureRequest::query()->where('deal_id', $deal->id)->where('uuid', $returnedFrom)->first();

                if ($request instanceof SignatureRequest) {
                    $this->signatures->refresh($request);
                }
            }

            $agent = $deal->user;
            $page = AgentPage::query()->where('user_id', $agent->id)->where('published', true)->first();
            $client = 'portal-'.$portal->uuid;
            $review = ClientReview::query()->where('client_portal_id', $portal->id)->first();

            return [
                'client' => $portal->name,
                'language' => $portal->language === 'es' ? 'es' : 'en',
                'agent' => [
                    'name' => (string) $agent->name,
                    'brokerage' => $this->compliance->brokerName($agent),
                    'phone' => $page?->phone,
                    'email' => $page?->email,
                    'page_url' => $page === null ? null : route('agent-pages.show', $page->handle),
                    'card_url' => $page === null ? null : route('agent-pages.card', $page->handle),
                ],
                'deal' => ['property' => $deal->property, 'city' => $deal->city, 'side' => $deal->side, 'stage' => $deal->stage, 'price' => $deal->price],
                'dates' => array_values(array_map(static fn (DealDate $d): array => ['kind' => $d->kind, 'label' => $d->label, 'due_on' => $d->due_on->toDateString(), 'done' => $d->done_at !== null], $deal->dates->sortBy('due_on')->all())),
                'documents' => array_map(static fn (array $d): array => ['name' => $d['name'], 'status' => $d['status']], $deal->documents),
                'signatures' => array_values(array_filter(array_map(static function (SignatureRequest $r) use ($client, $portal): ?array {
                    $mine = array_find($r->signers, static fn (array $s): bool => ($s['client_user_id'] ?? null) === $client || ($portal->email !== null && mb_strtolower($s['email']) === $portal->email));

                    if ($mine === null || $r->status === 'draft') {
                        return null;
                    }

                    $inPortal = ($mine['client_user_id'] ?? null) === $client;

                    return [
                        'id' => $r->uuid,
                        'subject' => $r->subject,
                        'status' => $r->status,
                        'mine' => $mine['status'],
                        'can_sign' => $inPortal && in_array($r->status, ['sent', 'delivered'], true) && in_array($mine['status'], ['sent', 'delivered'], true),
                        'in_portal' => $inPortal,
                    ];
                }, SignatureRequest::query()->where('deal_id', $deal->id)->orderBy('id')->get()->all()))),
                'review' => [
                    'asked' => $deal->stage === 'closed',
                    'mine' => $review === null ? null : ['rating' => $review->rating, 'comment' => $review->comment, 'publish' => $review->publish],
                ],
            ];
        });
    }

    /** DocuSign's signing view for the client, coming back to the portal. */
    public function sign(ClientPortal $portal, string $token, string $requestUuid): string
    {
        return $this->tenancy->run($portal->organisation_id, function () use ($portal, $token, $requestUuid): string {
            $request = SignatureRequest::query()->where('deal_id', $portal->deal_id)->where('uuid', $requestUuid)->first() ?? throw new NotFoundHttpException;

            return $this->signatures->signingUrl($request, 'portal-'.$portal->uuid, route('portal.show', ['token' => $token, 'signed' => $request->uuid]));
        });
    }

    /** The client's review after closing; once, changeable. */
    public function review(ClientPortal $portal, int $rating, ?string $comment, bool $publish): void
    {
        $this->tenancy->run($portal->organisation_id, function () use ($portal, $rating, $comment, $publish): void {
            $deal = Deal::query()->findOrFail($portal->deal_id);

            if ($deal->stage !== 'closed') {
                throw ValidationException::withMessages(['rating' => __('portal.errors.notClosed')]);
            }

            $parts = preg_split('/\s+/u', trim($portal->name)) ?: [];
            $shown = trim(($parts[0] ?? '').(count($parts) > 1 ? ' '.mb_substr((string) end($parts), 0, 1).'.' : ''));
            $comment = $comment === null || trim($comment) === '' ? null : trim($comment);

            ClientReview::query()->updateOrCreate(['client_portal_id' => $portal->id], [
                'uuid' => (string) Str::uuid7(), 'deal_id' => $deal->id, 'organisation_id' => $portal->organisation_id, 'user_id' => $deal->user_id,
                'rating' => max(1, min(5, $rating)), 'comment' => $comment, 'publish' => $publish, 'shown_name' => mb_substr($shown, 0, 60),
            ]);
        });
    }

    /**
     * Client reviews the clients allowed on the agent's public page.
     *
     * @return array{average: float, count: int, latest: list<array{name: string, rating: int, comment: string|null, date: string}>}|null
     */
    public function publicReviews(User $agent): ?array
    {
        $reviews = $this->tenancy->asPlatform(static fn () => ClientReview::query()->where('user_id', $agent->id)->where('publish', true)->latest('id')->get());

        if ($reviews->isEmpty()) {
            return null;
        }

        return [
            'average' => round((float) $reviews->avg('rating'), 1),
            'count' => $reviews->count(),
            'latest' => array_values(array_map(static fn (ClientReview $r): array => ['name' => $r->shown_name, 'rating' => $r->rating, 'comment' => $r->comment, 'date' => ($r->created_at ?? CarbonImmutable::now())->toDateString()], $reviews->take(3)->all())),
        ];
    }

    public function url(ClientPortal $portal): string
    {
        return route('portal.show', $portal->token);
    }

    /** @return PortalRow */
    private function row(ClientPortal $portal): array
    {
        $review = ClientReview::query()->where('client_portal_id', $portal->id)->first();

        return [
            'id' => $portal->uuid,
            'name' => $portal->name,
            'email' => $portal->email,
            'url' => $this->url($portal),
            'opened_at' => $portal->opened_at?->toIso8601String(),
            'sent_at' => $portal->sent_at?->toIso8601String(),
            'expires_at' => $portal->expires_at->toIso8601String(),
            'review' => $review === null ? null : ['rating' => $review->rating, 'publish' => $review->publish],
        ];
    }

    private function mine(User $agent, string $portalUuid): ClientPortal
    {
        $this->access->allow($agent);

        return ClientPortal::query()->where('user_id', $agent->id)->where('uuid', $portalUuid)->whereNull('revoked_at')->with('deal')->first() ?? throw new NotFoundHttpException;
    }
}
