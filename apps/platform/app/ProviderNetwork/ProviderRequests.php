<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\User;
use App\Notifications\ProviderRequestReceived;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Requests to connect (B16.1, ADR 0079, decision 3). A request is a request
 * to talk: never a referral, and never paid for (RESPA, the network rules).
 *
 * - **Limit per plan each month:** Listed 5, Pro 25, Partner 75, reset on
 *   the 1st (Central time). The limit is checked under a lock, so two
 *   requests at once can't pass it.
 * - **Only to agents in the provider's areas**, who haven't turned the
 *   network off.
 * - **Once at a time:** one waiting request per agent. A declined request
 *   is final; an expired one can be sent again after 30 days.
 * - **Expires after 14 days** unanswered, and doesn't come back to the agent.
 * - **A short note**, without phone numbers, email addresses or links:
 *   contacts are shared only when both agree (B16.2).
 *
 * @phpstan-type Sent array{id: string, agent: string, status: string, message: string|null, sent_at: string, expires_at: string}
 */
final readonly class ProviderRequests
{
    public const EXPIRES_DAYS = 14;

    public const AGAIN_DAYS = 30;

    private const CONTACT = [
        '/[\w.+-]+@[\w-]+\.[\w.-]+/u',
        '/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/',
        '/https?:\/\/|www\.|\b[\w-]+\.(?:com|net|org|io|co|us)\b/i',
    ];

    public function __construct(private AgentsInArea $area, private AuditLogger $audit) {}

    /** Whether the provider can ask this agent now, given the last request to them. */
    public static function canAsk(?ProviderRequest $last, CarbonImmutable $now): bool
    {
        return match ($last?->state()) {
            null, ProviderRequest::WITHDRAWN => true,
            ProviderRequest::EXPIRED => $last->expires_at->lte($now->subDays(self::AGAIN_DAYS)),
            default => false,
        };
    }

    public function send(User $provider, int $agentId, ?string $message, ?CarbonImmutable $now = null): ProviderRequest
    {
        $now ??= CarbonImmutable::now();
        $profile = $this->area->live($provider);
        $message = $message === null ? null : trim($message);
        $message = $message === '' ? null : $message;

        if ($message !== null && array_filter(self::CONTACT, static fn (string $re): bool => preg_match($re, $message) === 1) !== []) {
            throw ValidationException::withMessages(['message' => __('providers.agents.errors.contact')]);
        }

        if (! $this->area->includes($profile, $agentId, $now)) {
            throw new NotFoundHttpException;
        }

        $last = ProviderRequest::query()->where('provider_profile_id', $profile->id)->where('agent_id', $agentId)->latest('id')->first();

        if (! self::canAsk($last, $now)) {
            throw ValidationException::withMessages(['agent' => __('providers.agents.errors.'.($last?->state() === ProviderRequest::DECLINED ? 'declined' : ($last?->state() === ProviderRequest::EXPIRED ? 'again' : 'already')), ['days' => self::AGAIN_DAYS])]);
        }

        // One provider's requests one at a time: the count and the insert can't interleave.
        $request = Cache::lock("provider-requests:{$profile->id}", 10)->block(5, fn (): ProviderRequest => $this->create($profile, $provider, $agentId, $message, $now));

        if (! $request instanceof ProviderRequest) {
            throw new \LogicException('The request was not created.');
        }

        $agent = User::query()->findOrFail($agentId);
        $agent->notify(new ProviderRequestReceived($request, $profile));
        $this->audit->record('provider.request_sent', 'provider_request', $request->uuid, ['agent_id' => $agentId, 'category_id' => $profile->provider_category_id], $provider);

        return $request;
    }

    /** Checks the month's limit and records the request (under the provider's lock). */
    private function create(ProviderProfile $profile, User $provider, int $agentId, ?string $message, CarbonImmutable $now): ProviderRequest
    {
        $usage = $this->area->usage($profile, $now);

        if ($usage['left'] === 0) {
            throw ValidationException::withMessages(['limit' => $usage['limit'] === 0
                ? __('providers.agents.errors.noPlan')
                : __('providers.agents.errors.limit', ['limit' => $usage['limit'], 'date' => CarbonImmutable::parse($usage['resets_on'])->translatedFormat('F j')])]);
        }

        return ProviderRequest::query()->create([
            'uuid' => (string) Str::uuid7(), 'provider_profile_id' => $profile->id, 'provider_id' => $provider->id, 'agent_id' => $agentId,
            'provider_category_id' => $profile->provider_category_id, 'message' => $message, 'status' => ProviderRequest::PENDING,
            'expires_at' => $now->addDays(self::EXPIRES_DAYS),
        ]);
    }

    /** Takes back a waiting request (it still counts toward the month). */
    public function withdraw(User $provider, string $uuid): void
    {
        $profile = $this->area->live($provider);
        $request = ProviderRequest::query()->where('provider_profile_id', $profile->id)->where('uuid', $uuid)->firstOrFail();

        if ($request->state() !== ProviderRequest::PENDING) {
            throw ValidationException::withMessages(['request' => __('providers.agents.errors.notWaiting')]);
        }

        $request->forceFill(['status' => ProviderRequest::WITHDRAWN, 'responded_at' => now()])->save();
        $this->audit->record('provider.request_withdrawn', 'provider_request', $request->uuid, [], $provider);
    }

    /**
     * The provider's requests, newest first.
     *
     * @return list<Sent>
     */
    public function sent(User $provider): array
    {
        $profile = $this->area->live($provider);

        return array_values(ProviderRequest::query()->where('provider_profile_id', $profile->id)->with('agent:id,name')->latest('id')->limit(100)->get()
            ->map(static fn (ProviderRequest $r): array => [
                'id' => $r->uuid, 'agent' => AgentsInArea::shortName((string) $r->agent->name), 'status' => $r->state(), 'message' => $r->message,
                'sent_at' => $r->created_at?->toIso8601String() ?? '', 'expires_at' => $r->expires_at->toIso8601String(),
            ])->all());
    }

    /** Daily: waiting requests past their date become expired. Returns how many. */
    public function expire(?CarbonImmutable $now = null): int
    {
        return ProviderRequest::query()->where('status', ProviderRequest::PENDING)->where('expires_at', '<=', $now ?? CarbonImmutable::now())
            ->update(['status' => ProviderRequest::EXPIRED, 'updated_at' => now()]);
    }

    /** The profile behind a provider (for controllers that need the page's header). */
    public function profile(User $provider): ProviderProfile
    {
        return $this->area->live($provider);
    }
}
