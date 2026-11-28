<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Authorization\Role;
use App\Messaging\Conversations;
use App\Messaging\Safety;
use App\Models\Deal;
use App\Models\MlsMemberLink;
use App\Models\ProviderCategory;
use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\ProviderReview;
use App\Models\User;
use App\Notifications\AgentAskedToConnect;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Providers near you (B16.3, ADR 0081, agent journey): verified providers
 * serving the cities where the agent works, by category, with reviews
 * from agents who worked with them, and Request to connect in two taps.
 *
 * - **Near:** the cities and ZIP codes of the agent's REOS deals and
 *   confirmed MLS listings in 12 months, matched to providers' areas.
 *   With none yet, every live provider shows.
 * - **Connect:** an agent asking is agreeing, so the connection opens at
 *   once with a private chat (B16.2). It never counts toward the
 *   provider's monthly limit. Contacts still need both to press Share.
 * - **Reviews** (decision 5): only from an agent connected for 30 days,
 *   one per agent per provider (a second one replaces the first), 1 to 5
 *   stars and an optional note without contact details.
 *
 * @phpstan-type Rating array{average: float|null, count: int}
 * @phpstan-type Near array{id: int, name: string, category: string|null, category_key: string|null, photo_url: string|null, licence_verified: bool, areas: list<string>, response_hours: int|null, tier: string|null, rating: Rating, near: bool, connection: string|null}
 * @phpstan-type Network array{rating: Rating, reviews: list<array{id: string, rating: int, body: string|null, agent: string, at: string}>, connection: array{id: string, status: string}|null, can_connect: bool, can_review: bool, review_from: string|null, my_review: array{rating: int, body: string|null}|null}
 */
final readonly class AgentDirectory
{
    public const REVIEW_AFTER_DAYS = 30;

    private const CONTACT = [
        '/[\w.+-]+@[\w-]+\.[\w.-]+/u',
        '/(?:\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/',
        '/https?:\/\/|www\.|\b[\w-]+\.(?:com|net|org|io|co|us)\b/i',
    ];

    public function __construct(
        private ProviderProfiles $profiles,
        private ProviderConnections $connections,
        private Conversations $conversations,
        private Safety $safety,
        private ActiveRole $roles,
        private Tenancy $tenancy,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{cities: list<string>, categories: list<array{key: string, name: string, count: int}>, providers: list<Near>}
     */
    public function near(User $agent, ?string $category = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        [$cities, $zips] = $this->places($agent, $now);
        $profiles = ProviderProfile::query()->with(['category', 'areas', 'user'])->where('status', ProviderProfile::LIVE)->whereNotNull('published')
            ->whereNotIn('user_id', $this->safety->blockedWith($agent->id))->limit(300)->get();
        $ratings = $this->ratings(array_values($profiles->map(static fn (ProviderProfile $p): int => $p->id)->all()));
        $connected = ProviderRequest::query()->where('agent_id', $agent->id)->where('status', ProviderRequest::ACCEPTED)->pluck('uuid', 'provider_profile_id');
        $rows = [];
        $counts = [];

        foreach ($profiles as $p) {
            $near = $cities === [] && $zips === [] ? false : $p->areas->contains(static fn ($a): bool => ($a->city !== null && in_array(mb_strtolower($a->city), $cities, true)) || ($a->postal_code !== null && in_array($a->postal_code, $zips, true)));
            $key = $p->category?->key;

            if ($key !== null && ($near || $cities === [])) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            if ($category !== null && $key !== $category) {
                continue;
            }

            $card = $this->profiles->card($p, app()->getLocale());
            $link = $connected->get($p->id);
            $rows[] = [
                'id' => $p->id, 'name' => $card['name'], 'category' => $card['category'], 'category_key' => $key, 'photo_url' => $card['photo_url'],
                'licence_verified' => $card['licence']['verified'], 'areas' => $card['areas'], 'response_hours' => $card['response_hours'], 'tier' => $p->tier,
                'rating' => $ratings[$p->id] ?? ['average' => null, 'count' => 0], 'near' => $near, 'connection' => is_string($link) ? $link : null,
            ];
        }

        // Near first, then Partner and Pro, then the best reviewed.
        $tier = ['premium' => 0, 'pro' => 1, 'basic' => 2];
        usort($rows, static fn (array $a, array $b): int => [! $a['near'], $tier[$a['tier'] ?? 'basic'] ?? 2, -($a['rating']['average'] ?? 0)] <=> [! $b['near'], $tier[$b['tier'] ?? 'basic'] ?? 2, -($b['rating']['average'] ?? 0)]);
        $categories = [];

        foreach (ProviderCategory::query()->where('status', 'live')->orderBy('name')->get(['key', 'name']) as $c) {
            $categories[] = ['key' => $c->key, 'name' => $c->name, 'count' => $counts[$c->key] ?? 0];
        }

        return ['cities' => array_map(static fn (string $c): string => Str::title($c), $cities), 'categories' => $categories, 'providers' => $rows];
    }

    /**
     * What an agent sees beside a provider's profile: reviews, the connection and what they can do.
     *
     * @return Network
     */
    public function forProfile(User $viewer, int $profileId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $profile = $this->live($profileId);
        $connection = ProviderRequest::query()->where('agent_id', $viewer->id)->where('provider_profile_id', $profile->id)->latest('id')->first();
        $accepted = $connection?->status === ProviderRequest::ACCEPTED ? $connection : null;
        $from = $accepted?->responded_at === null ? null : CarbonImmutable::instance($accepted->responded_at)->addDays(self::REVIEW_AFTER_DAYS);
        $mine = ProviderReview::query()->where('provider_profile_id', $profile->id)->where('agent_id', $viewer->id)->first();
        $reviews = [];

        foreach (ProviderReview::query()->where('provider_profile_id', $profile->id)->with('agent:id,name')->latest('id')->limit(20)->get() as $r) {
            $reviews[] = ['id' => $r->uuid, 'rating' => $r->rating, 'body' => $r->body, 'agent' => AgentsInArea::shortName((string) $r->agent->name), 'at' => $r->created_at?->toIso8601String() ?? ''];
        }

        return [
            'rating' => $this->ratings([$profile->id])[$profile->id] ?? ['average' => null, 'count' => 0], 'reviews' => $reviews,
            'connection' => $connection === null ? null : ['id' => $connection->uuid, 'status' => $connection->state()],
            'can_connect' => $this->isAgent($viewer) && $accepted === null && ! $this->safety->between($viewer->id, $profile->user_id),
            'can_review' => $from !== null && $from->lte($now), 'review_from' => $from?->toDateString(),
            'my_review' => $mine === null ? null : ['rating' => $mine->rating, 'body' => $mine->body],
        ];
    }

    /**
     * Request to connect, the agent's second tap: connected at once, with a private chat.
     *
     * @return array<string, mixed> the connection (ProviderConnections)
     */
    public function connect(User $agent, int $profileId): array
    {
        if (! $this->isAgent($agent)) {
            throw new NotFoundHttpException;
        }

        $profile = $this->live($profileId);

        if ($this->safety->between($agent->id, $profile->user_id)) {
            throw ValidationException::withMessages(['provider' => __('providers.directory.connect.blocked')]);
        }

        $last = ProviderRequest::query()->where('agent_id', $agent->id)->where('provider_profile_id', $profile->id)->latest('id')->first();

        if ($last?->status === ProviderRequest::ACCEPTED) {
            return $this->connections->show($agent, $last->uuid);
        }

        // The provider already asked: connecting is accepting.
        if ($last !== null && $last->state() === ProviderRequest::PENDING) {
            return $this->connections->accept($agent, $last->uuid);
        }

        $request = ProviderRequest::query()->create([
            'uuid' => (string) Str::uuid7(), 'provider_profile_id' => $profile->id, 'provider_id' => $profile->user_id, 'agent_id' => $agent->id,
            'provider_category_id' => $profile->provider_category_id, 'status' => ProviderRequest::ACCEPTED, 'initiated_by' => ProviderRequest::BY_AGENT,
            'expires_at' => now(), 'responded_at' => now(),
        ]);
        $hello = __('providers.directory.connect.hello', ['name' => (string) $agent->name], $agent->preferredLocale());
        $this->conversations->start($agent, [$profile->user_id], is_string($hello) ? $hello : '');
        $profile->user->notify(new AgentAskedToConnect($request));
        $this->audit->record('provider.agent_connected', 'provider_request', $request->uuid, ['provider_id' => $profile->user_id], $agent);

        return $this->connections->show($agent, $request->uuid);
    }

    /**
     * Rates a provider after 30 days connected; a second review replaces the first.
     *
     * @return Network
     */
    public function review(User $agent, int $profileId, int $rating, ?string $body, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $profile = $this->live($profileId);
        $connection = ProviderRequest::query()->where('agent_id', $agent->id)->where('provider_profile_id', $profile->id)->where('status', ProviderRequest::ACCEPTED)->latest('id')->first();

        if ($connection === null || $connection->responded_at === null) {
            throw ValidationException::withMessages(['review' => __('providers.directory.reviews.errors.notConnected')]);
        }

        $from = CarbonImmutable::instance($connection->responded_at)->addDays(self::REVIEW_AFTER_DAYS);

        if ($from->gt($now)) {
            throw ValidationException::withMessages(['review' => __('providers.directory.reviews.errors.tooSoon', ['date' => $from->translatedFormat('F j')])]);
        }

        $body = $body === null || trim($body) === '' ? null : trim($body);

        if ($body !== null && array_filter(self::CONTACT, static fn (string $re): bool => preg_match($re, $body) === 1) !== []) {
            throw ValidationException::withMessages(['body' => __('providers.directory.reviews.errors.contact')]);
        }

        $review = ProviderReview::query()->updateOrCreate(
            ['provider_profile_id' => $profile->id, 'agent_id' => $agent->id],
            ['uuid' => ProviderReview::query()->where('provider_profile_id', $profile->id)->where('agent_id', $agent->id)->value('uuid') ?? (string) Str::uuid7(), 'provider_request_id' => $connection->id, 'rating' => max(1, min(5, $rating)), 'body' => $body],
        );
        $this->audit->record('provider.reviewed', 'provider_review', $review->uuid, ['provider_profile_id' => $profile->id, 'rating' => $review->rating], $agent);

        return $this->forProfile($agent, $profile->id, $now);
    }

    private function isAgent(User $user): bool
    {
        return $this->roles->for($user)?->role === Role::Agent;
    }

    private function live(int $id): ProviderProfile
    {
        $profile = ProviderProfile::query()->with(['category', 'areas', 'user'])->find($id);

        if ($profile === null || $profile->status !== ProviderProfile::LIVE || $profile->published === null) {
            throw new NotFoundHttpException;
        }

        return $profile;
    }

    /**
     * Where the agent works: lower-case cities and ZIP codes from 12 months of deals and listings.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function places(User $agent, CarbonImmutable $now): array
    {
        $since = $now->subYear()->toDateString();
        $cities = [];
        $zips = [];

        foreach ($this->tenancy->asPlatform(static fn () => Deal::query()->where('user_id', $agent->id)->whereNotNull('city')
            ->where(static fn ($q) => $q->whereIn('stage', ['active', 'under_contract'])->orWhere('closed_at', '>=', $since)->orWhere('created_at', '>=', $since))
            ->limit(500)->pluck('city')) as $city) {
            if (is_string($city) && trim($city) !== '') {
                $cities[mb_strtolower(trim($city))] = true;
            }
        }

        $links = MlsMemberLink::query()->where('user_id', $agent->id)->where('status', MlsMemberLink::CONFIRMED)->get(['integration', 'member_key']);

        foreach ($links as $link) {
            foreach (DB::table('mls_listings')->where('integration', $link->integration)
                ->where(static fn ($q) => $q->where('list_agent_key', $link->member_key)->orWhere('co_list_agent_key', $link->member_key))
                ->where(static fn ($q) => $q->where('listed_on', '>=', $since)->orWhere('close_date', '>=', $since)->orWhere('status', 'active'))
                ->limit(500)->get(['city', 'postal_code']) as $row) {
                if (is_string($row->city) && trim($row->city) !== '') {
                    $cities[mb_strtolower(trim($row->city))] = true;
                }

                if (is_string($row->postal_code) && strlen($row->postal_code) >= 5) {
                    $zips[substr($row->postal_code, 0, 5)] = true;
                }
            }
        }

        return [array_keys($cities), array_keys($zips)];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{average: float|null, count: int}>
     */
    private function ratings(array $ids): array
    {
        $out = [];

        foreach (DB::table('provider_reviews')->whereIn('provider_profile_id', $ids)->groupBy('provider_profile_id')->selectRaw('provider_profile_id, avg(rating) as average, count(*) as total')->get() as $row) {
            if (is_numeric($row->provider_profile_id) && is_numeric($row->average) && is_numeric($row->total)) {
                $out[(int) $row->provider_profile_id] = ['average' => round((float) $row->average, 1), 'count' => (int) $row->total];
            }
        }

        return $out;
    }
}
