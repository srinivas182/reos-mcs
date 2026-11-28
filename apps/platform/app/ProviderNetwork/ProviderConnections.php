<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Messaging\Conversations;
use App\Messaging\Safety;
use App\Models\Conversation;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\User;
use App\Notifications\ProviderRequestAnswered;
use App\Privacy\ConsentPurpose;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\Consents;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The private network between providers and agents (B16.2, ADR 0080).
 *
 * - **The agent answers a request:** Accept opens a private chat with the
 *   provider (Messages); Decline ends it, and Decline and block also stops
 *   the provider asking again or messaging.
 * - **Mutual contact sharing:** each side presses Share (a consent per
 *   connection, `contact_sharing` with subject `provider_request:<id>`).
 *   Phone and email show only when both have shared, and either can stop
 *   sharing at any time.
 * - **Block and report:** block from a request or a connection; report a
 *   connection's chat through Messages (B6.1b) to REOS's team.
 * - **Opt out:** from all providers (the provider-network switch) or per
 *   category (the same consent with subject `category:<key>`).
 *
 * @phpstan-type Card array{id: int, name: string, category: string|null, photo_url: string|null}
 * @phpstan-type Pending array{id: string, provider: Card, message: string|null, sent_at: string, expires_at: string}
 * @phpstan-type Connection array{id: string, side: 'agent'|'provider', with: string, provider: Card, connected_at: string|null, i_shared: bool, they_shared: bool, contacts: array{email: string|null, phone: string|null}|null, conversation: string|null, blocked: bool}
 * @phpstan-type Preferences array{all: bool, categories: list<array{key: string, name: string, on: bool}>}
 */
final readonly class ProviderConnections
{
    public function __construct(
        private Consents $consents,
        private Conversations $conversations,
        private Safety $safety,
        private ProviderProfiles $profiles,
        private AuditLogger $audit,
    ) {}

    /**
     * The agent's waiting requests, connections and choices.
     *
     * @return array{pending: list<Pending>, connections: list<Connection>, preferences: Preferences}
     */
    public function forAgent(User $agent, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $rows = ProviderRequest::query()->where('agent_id', $agent->id)->with(['profile.category', 'profile.user', 'profile.areas'])->latest('id')->limit(200)->get();
        $pending = [];
        $connections = [];

        foreach ($rows as $r) {
            if ($r->state() === ProviderRequest::PENDING && ! $this->safety->between($agent->id, $r->provider_id)) {
                $pending[] = [
                    'id' => $r->uuid, 'provider' => $this->card($r->profile), 'message' => $r->message,
                    'sent_at' => $r->created_at?->toIso8601String() ?? '', 'expires_at' => $r->expires_at->toIso8601String(),
                ];
            } elseif ($r->status === ProviderRequest::ACCEPTED) {
                $connections[] = $this->connection($agent, $r);
            }
        }

        return ['pending' => $pending, 'connections' => $connections, 'preferences' => $this->preferences($agent)];
    }

    /**
     * The provider's connections (accepted requests).
     *
     * @return list<Connection>
     */
    public function forProvider(User $provider): array
    {
        $out = [];

        foreach (ProviderRequest::query()->where('provider_id', $provider->id)->where('status', ProviderRequest::ACCEPTED)
            ->with(['profile.category', 'profile.user', 'profile.areas', 'agent'])->latest('responded_at')->limit(200)->get() as $r) {
            $out[] = $this->connection($provider, $r);
        }

        return $out;
    }

    /** @return Connection */
    public function accept(User $agent, string $uuid): array
    {
        $request = $this->waiting($agent, $uuid);
        $request->forceFill(['status' => ProviderRequest::ACCEPTED, 'responded_at' => now()])->save();
        $provider = $request->provider;
        $hello = __('providers.network.hello', ['name' => (string) $agent->name], $agent->preferredLocale());
        // The private chat: providers can message an agent only once the agent accepts (Relationships).
        $this->conversations->start($agent, [$provider->id], is_string($hello) ? $hello : '');
        $provider->notify(new ProviderRequestAnswered($request, true));
        $this->audit->record('provider.request_accepted', 'provider_request', $request->uuid, ['provider_id' => $provider->id], $agent);

        return $this->connection($agent, $request->refresh());
    }

    public function decline(User $agent, string $uuid, bool $block): void
    {
        $request = $this->waiting($agent, $uuid);
        $request->forceFill(['status' => ProviderRequest::DECLINED, 'responded_at' => now()])->save();

        if ($block) {
            $this->safety->block($agent, $request->provider);
        }

        // Told without the reason, and without saying whether they were blocked.
        $request->provider->notify(new ProviderRequestAnswered($request, false));
        $this->audit->record('provider.request_declined', 'provider_request', $request->uuid, ['blocked' => $block], $agent);
    }

    /** @return Connection */
    public function show(User $user, string $uuid): array
    {
        return $this->connection($user, $this->connected($user, $uuid));
    }

    /**
     * Share (or stop sharing) my phone and email with the other side.
     *
     * @return Connection
     */
    public function share(User $user, string $uuid, bool $share): array
    {
        $request = $this->connected($user, $uuid);
        $subject = self::subject($request);
        $share
            ? $this->consents->grant($user, ConsentPurpose::ContactSharing, $this->consents->method(), $subject)
            : $this->consents->withdraw($user, ConsentPurpose::ContactSharing, $this->consents->method(), $subject);
        $this->audit->record($share ? 'provider.contacts_shared' : 'provider.contacts_unshared', 'provider_request', $request->uuid, [], $user);

        return $this->connection($user, $request);
    }

    /** Blocks the other side of a connection: no more messages or requests. */
    public function block(User $user, string $uuid): void
    {
        $request = $this->connected($user, $uuid);
        $other = $user->id === $request->agent_id ? $request->provider : $request->agent;
        $this->safety->block($user, $other);
        $this->audit->record('provider.blocked', 'provider_request', $request->uuid, [], $user);
    }

    /** @return Preferences */
    public function preferences(User $agent): array
    {
        $categories = [];

        foreach (ProviderCategory::query()->where('status', 'live')->orderBy('name')->get(['key', 'name']) as $c) {
            $categories[] = ['key' => $c->key, 'name' => $c->name, 'on' => $this->consents->allows($agent, ConsentPurpose::ProviderNetwork, 'category:'.$c->key) || $this->consents->latest($agent, ConsentPurpose::ProviderNetwork, 'category:'.$c->key) === null];
        }

        return ['all' => $this->consents->allows($agent, ConsentPurpose::ProviderNetwork), 'categories' => $categories];
    }

    /**
     * @param  list<string>  $off  category keys turned off
     * @return Preferences
     */
    public function setPreferences(User $agent, bool $all, array $off): array
    {
        $before = $this->preferences($agent);
        $method = $this->consents->method();

        if ($before['all'] !== $all) {
            $all ? $this->consents->grant($agent, ConsentPurpose::ProviderNetwork, $method) : $this->consents->withdraw($agent, ConsentPurpose::ProviderNetwork, $method);
        }

        foreach ($before['categories'] as $c) {
            $on = ! in_array($c['key'], $off, true);

            if ($c['on'] !== $on) {
                $on ? $this->consents->grant($agent, ConsentPurpose::ProviderNetwork, $method, 'category:'.$c['key'])
                    : $this->consents->withdraw($agent, ConsentPurpose::ProviderNetwork, $method, 'category:'.$c['key']);
            }
        }

        return $this->preferences($agent);
    }

    /** The consent subject for one connection's contact sharing. */
    public static function subject(ProviderRequest $request): string
    {
        return 'provider_request:'.$request->uuid;
    }

    /** @return Connection */
    private function connection(User $viewer, ProviderRequest $r): array
    {
        $agentSide = $viewer->id === $r->agent_id;
        $other = $agentSide ? $r->provider : $r->agent;
        $subject = self::subject($r);
        $mine = $this->consents->allows($viewer, ConsentPurpose::ContactSharing, $subject);
        $theirs = $this->consents->allows($other, ConsentPurpose::ContactSharing, $subject);
        $blocked = $this->safety->between($r->agent_id, $r->provider_id);
        $conversation = $this->conversationBetween($r->agent_id, $r->provider_id);

        return [
            'id' => $r->uuid, 'side' => $agentSide ? 'agent' : 'provider', 'with' => (string) $other->name, 'provider' => $this->card($r->profile),
            'connected_at' => $r->responded_at?->toIso8601String(), 'i_shared' => $mine, 'they_shared' => $theirs,
            // Only when both agreed, and never across a block.
            'contacts' => $mine && $theirs && ! $blocked ? ['email' => $other->email, 'phone' => $other->phone] : null,
            'conversation' => $blocked ? null : $conversation, 'blocked' => $blocked,
        ];
    }

    /** @return Card */
    private function card(ProviderProfile $profile): array
    {
        $card = $this->profiles->card($profile, app()->getLocale());

        return ['id' => $card['id'], 'name' => $card['name'], 'category' => $card['category'], 'photo_url' => $card['photo_url']];
    }

    private function waiting(User $agent, string $uuid): ProviderRequest
    {
        $request = ProviderRequest::query()->where('agent_id', $agent->id)->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;

        if ($request->state() !== ProviderRequest::PENDING) {
            throw ValidationException::withMessages(['request' => __('providers.agents.errors.notWaiting')]);
        }

        return $request;
    }

    private function connected(User $user, string $uuid): ProviderRequest
    {
        return ProviderRequest::query()->where('uuid', $uuid)->where('status', ProviderRequest::ACCEPTED)
            ->where(static fn ($q) => $q->where('agent_id', $user->id)->orWhere('provider_id', $user->id))
            ->with(['profile.category', 'profile.user', 'profile.areas', 'agent', 'provider'])->first() ?? throw new NotFoundHttpException;
    }

    private function conversationBetween(int $a, int $b): ?string
    {
        $uuid = Conversation::query()->where('kind', Conversation::DIRECT)
            ->whereHas('participants', static fn ($q) => $q->where('user_id', $a))
            ->whereHas('participants', static fn ($q) => $q->where('user_id', $b))
            ->value('uuid');

        return is_string($uuid) ? $uuid : null;
    }
}
