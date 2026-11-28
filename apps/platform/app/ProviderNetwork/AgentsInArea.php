<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\MlsMemberLink;
use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Models\ProviderServiceArea;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agents in my areas (B16.1, ADR 0079, provider journey): the REOS agents
 * working in a live provider's service areas, by activity level.
 *
 * - **Where an agent works:** their MLS listings (as listing or co-listing
 *   agent, from the member record they confirmed) and their REOS deals in
 *   the last 12 months, matched to the provider's cities, ZIP codes and
 *   radii.
 * - **Level:** *top producer* with 12 or more in 12 months; *new* when
 *   first licensed in the last 12 months; *active* otherwise.
 * - **What the provider sees:** first name and initial, brokerage, level,
 *   the cities and how many in 12 months. Never contacts, clients or
 *   deals (contacts come only when both share, B16.2).
 * - **Who's left out:** agents who turned the provider network off
 *   (Settings › Privacy), and people without an agent role.
 *
 * @phpstan-type Level 'new'|'active'|'top'
 * @phpstan-type AgentRow array{id: int, name: string, brokerage: string|null, level: Level, sides: int, cities: list<string>, request: array{id: string, status: string, sent_at: string}|null, can_request: bool}
 * @phpstan-type Usage array{tier: string|null, limit: int, used: int, left: int, resets_on: string}
 * @phpstan-type View array{areas: list<array{id: int, label: string}>, filters: array{level: Level|null, area: int|null}, counts: array{new: int, active: int, top: int}, agents: list<AgentRow>, usage: Usage}
 */
final readonly class AgentsInArea
{
    public const LEVELS = ['new', 'active', 'top'];

    /** Listings and deals in 12 months that make a top producer. */
    public const TOP = 12;

    public const LIMIT = 100;

    /** The month, and its reset, run on Central time (the network is in Texas first). */
    public const TIMEZONE = 'America/Chicago';

    public function __construct(
        private ProviderOnboarding $onboarding,
        private Memberships $memberships,
        private Tenancy $tenancy,
    ) {}

    /**
     * @param  Level|null  $level
     * @return View
     */
    public function for(User $provider, ?string $level = null, ?int $areaId = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $profile = $this->live($provider);
        $areas = $profile->areas()->orderBy('id')->get();
        $chosen = $areaId === null ? $areas : $areas->where('id', $areaId)->values();
        $rows = $this->rows($profile, array_values($chosen->all()), $now);
        $counts = ['new' => 0, 'active' => 0, 'top' => 0];

        foreach ($rows as $row) {
            $counts[$row['level']]++;
        }

        return [
            'areas' => array_values($areas->map(static fn (ProviderServiceArea $a): array => ['id' => $a->id, 'label' => ProviderProfiles::areaLabel($a)])->all()),
            'filters' => ['level' => $level, 'area' => $areaId],
            'counts' => $counts,
            'agents' => array_slice(array_values(array_filter($rows, static fn (array $r): bool => $level === null || $r['level'] === $level)), 0, self::LIMIT),
            'usage' => $this->usage($profile, $now),
        ];
    }

    /** Whether an agent is in the provider's areas now (requests go only to them). */
    public function includes(ProviderProfile $profile, int $agentId, ?CarbonImmutable $now = null): bool
    {
        foreach ($this->rows($profile, array_values($profile->areas()->get()->all()), $now ?? CarbonImmutable::now()) as $row) {
            if ($row['id'] === $agentId) {
                return true;
            }
        }

        return false;
    }

    /** @return Usage */
    public function usage(ProviderProfile $profile, ?CarbonImmutable $now = null): array
    {
        $local = ($now ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);
        $limit = ProviderPlans::requests($profile->tier);
        $used = ProviderRequest::query()->where('provider_profile_id', $profile->id)->where('created_at', '>=', $local->startOfMonth()->utc())->count();

        return ['tier' => $profile->tier, 'limit' => $limit, 'used' => $used, 'left' => max(0, $limit - $used), 'resets_on' => $local->startOfMonth()->addMonth()->toDateString()];
    }

    /** The provider's profile, which must be live to see agents. */
    public function live(User $provider): ProviderProfile
    {
        $profile = $this->onboarding->profile($provider);

        if ($profile->status !== ProviderProfile::LIVE) {
            throw ValidationException::withMessages(['profile' => __('providers.agents.errors.notLive')]);
        }

        return $profile;
    }

    /**
     * @param  list<ProviderServiceArea>  $areas
     * @return list<AgentRow>
     */
    private function rows(ProviderProfile $profile, array $areas, CarbonImmutable $now): array
    {
        /** @var array<int, array{sides: int, cities: array<string, int>}> $work */
        $work = [];
        $since = $now->subYear()->toDateString();

        // MLS listings in the areas, mapped to the agents who confirmed their member record.
        $listings = [];

        foreach ($areas as $area) {
            foreach ($this->listingsIn($area, $since) as $key => $listing) {
                $listings[$key] = $listing;
            }
        }

        $members = [];

        foreach ($listings as $l) {
            foreach (array_filter([$l['agent'], $l['co_agent']]) as $memberKey) {
                $members[$l['integration']][$memberKey] = true;
            }
        }

        $owners = [];

        foreach ($members as $integration => $keys) {
            foreach (MlsMemberLink::query()->where('integration', $integration)->whereIn('member_key', array_keys($keys))->where('status', MlsMemberLink::CONFIRMED)->get(['user_id', 'member_key']) as $link) {
                $owners[$integration][$link->member_key][] = $link->user_id;
            }
        }

        foreach ($listings as $l) {
            $users = [];

            foreach (array_filter([$l['agent'], $l['co_agent']]) as $memberKey) {
                foreach ($owners[$l['integration']][$memberKey] ?? [] as $userId) {
                    $users[$userId] = true;
                }
            }

            foreach (array_keys($users) as $userId) {
                self::count($work, $userId, $l['city']);
            }
        }

        // REOS deals in the areas' cities.
        $cities = array_values(array_unique(array_filter(array_map(static fn (ProviderServiceArea $a): ?string => $a->kind === 'city' && $a->city !== null ? mb_strtolower($a->city) : null, $areas))));

        if ($cities !== []) {
            $deals = $this->tenancy->asPlatform(static fn () => Deal::query()->whereIn(DB::raw('lower(city)'), $cities)
                ->where(static fn ($q) => $q->whereIn('stage', ['active', 'under_contract'])->orWhere('closed_at', '>=', $since)->orWhere('created_at', '>=', $since))
                ->limit(20000)->get(['user_id', 'city']));

            foreach ($deals as $deal) {
                self::count($work, $deal->user_id, $deal->city);
            }
        }

        if ($work === []) {
            return [];
        }

        $agentIds = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', Role::Agent->value)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())->whereIn('model_has_roles.model_id', array_keys($work))->distinct()->pluck('model_has_roles.model_id');
        $agents = User::query()->whereIn('id', $agentIds)->get(['id', 'name']);
        $optedOut = $this->optedOut(array_values($agents->map(static fn (User $u): int => $u->id)->all()));
        $firstLicensed = Licence::query()->whereIn('user_id', $agents->pluck('id'))->groupBy('user_id')->selectRaw('user_id, min(licensed_on) as first')->pluck('first', 'user_id');
        $requests = ProviderRequest::query()->where('provider_profile_id', $profile->id)->whereIn('agent_id', $agents->pluck('id'))->orderBy('id')->get()->keyBy('agent_id');
        $rows = [];

        foreach ($agents as $agent) {
            if (isset($optedOut[$agent->id])) {
                continue;
            }

            $w = $work[$agent->id];
            arsort($w['cities']);
            $first = $firstLicensed->get($agent->id);
            $new = is_string($first) && CarbonImmutable::parse($first)->gte($now->subYear());
            $request = $requests->get($agent->id);

            $rows[] = [
                'id' => $agent->id, 'name' => self::shortName((string) $agent->name), 'brokerage' => $this->brokerageOf($agent),
                'level' => $w['sides'] >= self::TOP ? 'top' : ($new ? 'new' : 'active'), 'sides' => $w['sides'],
                'cities' => array_slice(array_keys($w['cities']), 0, 3),
                'request' => $request instanceof ProviderRequest ? ['id' => $request->uuid, 'status' => $request->state(), 'sent_at' => $request->created_at?->toIso8601String() ?? ''] : null,
                'can_request' => ProviderRequests::canAsk($request, $now),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['sides'], $a['name']] <=> [$a['sides'], $b['name']]);

        return $rows;
    }

    /**
     * Listings in an area in the last 12 months, keyed so a listing counts once.
     *
     * @return array<string, array{integration: string, agent: string|null, co_agent: string|null, city: string}>
     */
    private function listingsIn(ProviderServiceArea $area, string $since): array
    {
        $q = DB::table('mls_listings')->where(static fn (Builder $w) => $w->whereNotNull('list_agent_key')->orWhereNotNull('co_list_agent_key'))
            ->where(static fn (Builder $w) => $w->where('listed_on', '>=', $since)->orWhere('close_date', '>=', $since)->orWhere('status', 'active'));
        $centre = null;

        if ($area->kind === 'city') {
            $q->whereRaw('lower(city) = ?', [mb_strtolower((string) $area->city)]);
        } elseif ($area->kind === 'zip') {
            $q->where('postal_code', 'like', $area->postal_code.'%');
        } else {
            $centre = ServiceAreas::centre($area);

            if ($centre === null) {
                return [];
            }

            $miles = (int) $area->radius_miles;
            $dLat = $miles / 69.0;
            $dLng = $miles / max(1.0, 69.0 * cos(deg2rad($centre[0])));
            $q->whereBetween('latitude', [$centre[0] - $dLat, $centre[0] + $dLat])->whereBetween('longitude', [$centre[1] - $dLng, $centre[1] + $dLng]);
        }

        $out = [];

        foreach ($q->limit(20000)->get(['integration', 'listing_key', 'list_agent_key', 'co_list_agent_key', 'city', 'latitude', 'longitude']) as $r) {
            if ($centre !== null && (! is_numeric($r->latitude) || ! is_numeric($r->longitude) || ServiceAreas::miles($centre[0], $centre[1], (float) $r->latitude, (float) $r->longitude) > (int) $area->radius_miles)) {
                continue;
            }

            $out[$r->integration.'|'.$r->listing_key] = [
                'integration' => (string) $r->integration, 'agent' => is_string($r->list_agent_key) ? $r->list_agent_key : null,
                'co_agent' => is_string($r->co_list_agent_key) ? $r->co_list_agent_key : null, 'city' => is_string($r->city) ? $r->city : '',
            ];
        }

        return $out;
    }

    /**
     * People whose latest provider-network choice is off.
     *
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function optedOut(array $ids): array
    {
        $latest = DB::table('consent_records')->whereIn('user_id', $ids)->where('purpose', 'provider_network')->whereNull('subject')
            ->orderBy('user_id')->orderByDesc('id')->get(['user_id', 'granted'])->unique('user_id');
        $out = [];

        foreach ($latest as $row) {
            if (! (bool) $row->granted) {
                $out[(int) $row->user_id] = true;
            }
        }

        return $out;
    }

    private function brokerageOf(User $agent): ?string
    {
        foreach ($this->memberships->of($agent) as $m) {
            if ($m->role === Role::Agent) {
                return $m->organisation->type === OrganisationType::Team && $m->organisation->parent !== null ? $m->organisation->parent->name : $m->organisation->name;
            }
        }

        return null;
    }

    /** "James M.": the provider sees the full name only once connected. */
    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1).'.' : '';

        return trim($first.' '.$last);
    }

    /** @param array<int, array{sides: int, cities: array<string, int>}> $work */
    private static function count(array &$work, int $userId, ?string $city): void
    {
        $work[$userId] ??= ['sides' => 0, 'cities' => []];
        $work[$userId]['sides']++;
        $name = trim((string) $city);

        if ($name !== '') {
            $work[$userId]['cities'][$name] = ($work[$userId]['cities'][$name] ?? 0) + 1;
        }
    }
}
