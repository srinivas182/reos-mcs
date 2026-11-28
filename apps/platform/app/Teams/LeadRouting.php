<?php

declare(strict_types=1);

namespace App\Teams;

use App\Authorization\Role;
use App\Brokerage\BrokerageAccess;
use App\Leads\Leads;
use App\Models\Organisation;
use App\Models\RoutedLead;
use App\Models\User;
use App\Notifications\LeadOffered;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lead routing for a team (A17.3, ADR 0084; team leader screens 28–29).
 *
 * - **Rules:** the sources it applies to (empty for all), the method
 *   (first to claim or round robin), the claim time (1 to 30 minutes),
 *   the fallback (round robin or the team leader) and off hours.
 * - **First to claim:** every active agent in the rotation who speaks the
 *   lead's language (or everyone, when none does) is told at once; the
 *   first to claim within the time gets it. Nobody in time: the fallback.
 * - **Round robin:** the active agent assigned longest ago.
 * - **Off hours** (8 pm to 8 am Central by default): routing still runs,
 *   and the lead gets a reply that names the team and offers a call.
 * - A claimed or assigned lead goes to the agent's CRM as a contact with a
 *   follow-up task (B14). Every hand-off is kept for the team leader.
 *
 * @phpstan-type Rules array{sources: list<string>, method: 'first_to_claim'|'round_robin', claim_minutes: int, fallback: 'round_robin'|'leader', off_start: string, off_end: string}
 * @phpstan-type Member array{id: int, name: string, active: bool, languages: list<string>, last_assigned_at: string|null}
 * @phpstan-type LeadRow array{id: string, source: string, name: string, language: string|null, status: string, how: string|null, agent: string|null, claim_by: string|null, created_at: string, auto_reply: string|null}
 * @phpstan-type Board array{team: string, rules: Rules, members: list<Member>, leads: list<LeadRow>}
 */
final readonly class LeadRouting
{
    public const SOURCES = ['website', 'zillow', 'realtor_com', 'open_house', 'referral', 'other'];

    public const DEFAULTS = ['sources' => [], 'method' => 'first_to_claim', 'claim_minutes' => 5, 'fallback' => 'round_robin', 'off_start' => '20:00', 'off_end' => '08:00'];

    public const TIMEZONE = 'America/Chicago';

    public function __construct(
        private BrokerageAccess $access,
        private Memberships $memberships,
        private Tenancy $tenancy,
        private Leads $leads,
        private AuditLogger $audit,
    ) {}

    /** @return Board */
    public function board(User $leader): array
    {
        $team = $this->team($leader);

        return $this->tenancy->run($team->id, fn (): array => [
            'team' => $team->name, 'rules' => self::rules($team), 'members' => $this->members($team),
            'leads' => array_values(array_map(fn (RoutedLead $l): array => $this->row($l), RoutedLead::query()->where('organisation_id', $team->id)->latest('id')->limit(50)->get()->all())),
        ]);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return Board
     */
    public function setRules(User $leader, array $rules): array
    {
        $team = $this->team($leader);
        $before = self::rules($team);
        $team->forceFill(['lead_routing' => [...$before, ...array_intersect_key($rules, self::DEFAULTS)]])->save();
        $this->audit->record('team.routing_changed', 'organisation', (string) $team->id, ['before' => $before, 'after' => self::rules($team)], $leader, $team->id);

        return $this->board($leader);
    }

    /**
     * Turns an agent in the rotation on or off, with their languages.
     *
     * @param  list<string>  $languages
     * @return Board
     */
    public function setMember(User $leader, int $agentId, bool $active, array $languages): array
    {
        $team = $this->team($leader);
        $this->memberships->holdersIn(Role::Agent, $team)->firstWhere('id', $agentId) ?? throw new NotFoundHttpException;
        $this->tenancy->run($team->id, static fn () => DB::table('lead_routing_members')->updateOrInsert(
            ['organisation_id' => $team->id, 'user_id' => $agentId],
            ['active' => $active, 'languages' => json_encode($languages, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now()],
        ));
        $this->audit->record('team.rotation_changed', 'user', (string) $agentId, ['active' => $active], $leader, $team->id);

        return $this->board($leader);
    }

    /**
     * A lead arrives for the team: offered to claim, or assigned in turn.
     *
     * @param  array{source: string, name: string, email?: string|null, phone?: string|null, language?: string|null, message?: string|null}  $lead
     */
    public function receive(Organisation $team, array $lead, ?CarbonImmutable $now = null): RoutedLead
    {
        $now ??= CarbonImmutable::now();
        $rules = self::rules($team);
        $offHours = self::offHours($rules, $now);

        return $this->tenancy->run($team->id, function () use ($team, $lead, $rules, $offHours, $now): RoutedLead {
            $routed = RoutedLead::query()->create([
                'uuid' => (string) Str::uuid7(), 'organisation_id' => $team->id, 'source' => $lead['source'], 'name' => Str::limit(trim($lead['name']), 160, ''),
                'email' => $lead['email'] ?? null, 'phone' => $lead['phone'] ?? null, 'language' => $lead['language'] ?? null, 'message' => $lead['message'] ?? null,
                'status' => 'offered', 'auto_reply' => $offHours ? self::autoReply($team, $lead['language'] ?? null) : null,
            ]);
            $active = $this->active($team);

            if ($active === []) {
                return $this->toLeader($team, $routed);
            }

            if ($rules['method'] === 'round_robin' || ($rules['sources'] !== [] && ! in_array($lead['source'], $rules['sources'], true))) {
                return $this->assign($team, $routed, $this->next($team, $active), 'round_robin');
            }

            $speakers = array_values(array_filter($active, static fn (array $m): bool => $routed->language === null || in_array($routed->language, $m['languages'], true)));
            $offered = $speakers === [] ? $active : $speakers;
            $routed->forceFill(['offered_to' => array_column($offered, 'id'), 'claim_by' => $now->addMinutes($rules['claim_minutes'])])->save();
            Notification::send(User::query()->whereIn('id', array_column($offered, 'id'))->get(), new LeadOffered($routed, $team->name, $rules['claim_minutes']));

            return $routed;
        });
    }

    /** The first to claim in time gets the lead; everyone else is told it's taken. */
    public function claim(User $agent, string $uuid, ?CarbonImmutable $now = null): RoutedLead
    {
        $now ??= CarbonImmutable::now();

        $claimed = Cache::lock("lead-claim:{$uuid}", 10)->block(5, function () use ($agent, $uuid, $now): RoutedLead {
            $lead = $this->tenancy->asPlatform(static fn () => RoutedLead::query()->where('uuid', $uuid)->first()) ?? throw new NotFoundHttpException;

            if (! in_array($agent->id, $lead->offered_to ?? [], true)) {
                throw new NotFoundHttpException;
            }

            if ($lead->status !== 'offered') {
                throw ValidationException::withMessages(['lead' => __('teams.routing.errors.taken')]);
            }

            if ($lead->claim_by !== null && $now->gt($lead->claim_by)) {
                throw ValidationException::withMessages(['lead' => __('teams.routing.errors.expired')]);
            }

            $team = Organisation::query()->findOrFail($lead->organisation_id);

            return $this->tenancy->run($team->id, fn (): RoutedLead => $this->assign($team, $lead, $agent, 'claim', 'claimed'));
        });

        return $claimed instanceof RoutedLead ? $claimed : throw new NotFoundHttpException;
    }

    /**
     * Claims and answers with what the app needs.
     *
     * @return array{id: string, status: string, contact: string|null}
     */
    public function claimRow(User $agent, string $uuid): array
    {
        $claimed = $this->claim($agent, $uuid);

        return ['id' => $claimed->uuid, 'status' => $claimed->status, 'contact' => $claimed->contact_uuid];
    }

    /** Every minute: leads nobody claimed in time go to the fallback. Returns how many. */
    public function sweep(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $count = 0;

        foreach ($this->tenancy->asPlatform(static fn () => RoutedLead::query()->where('status', 'offered')->where('claim_by', '<', $now)->limit(200)->get()) as $lead) {
            $team = Organisation::query()->find($lead->organisation_id);

            if ($team === null) {
                continue;
            }

            $this->tenancy->run($team->id, function () use ($team, $lead): void {
                $rules = self::rules($team);
                $others = array_values(array_filter($this->active($team), static fn (array $m): bool => ! in_array($m['id'], $lead->offered_to ?? [], true)));
                $pool = $others === [] ? $this->active($team) : $others;

                $rules['fallback'] === 'leader' || $pool === []
                    ? $this->toLeader($team, $lead)
                    : $this->assign($team, $lead, $this->next($team, $pool), 'fallback');
            });
            $count++;
        }

        return $count;
    }

    /**
     * Leads offered to this agent right now (Claim).
     *
     * @return list<LeadRow>
     */
    public function offeredTo(User $agent): array
    {
        $rows = [];

        foreach ($this->tenancy->asPlatform(static fn () => RoutedLead::query()->where('status', 'offered')->whereJsonContains('offered_to', $agent->id)->where('claim_by', '>=', now())->latest('id')->limit(20)->get()) as $lead) {
            $rows[] = $this->row($lead);
        }

        return $rows;
    }

    /** @return Rules */
    public static function rules(Organisation $team): array
    {
        $r = [...self::DEFAULTS, ...($team->lead_routing ?? [])];
        $sources = is_array($r['sources']) ? array_values(array_filter($r['sources'], static fn (mixed $s): bool => is_string($s) && in_array($s, self::SOURCES, true))) : [];

        return [
            'sources' => $sources,
            'method' => $r['method'] === 'round_robin' ? 'round_robin' : 'first_to_claim',
            'claim_minutes' => max(1, min(30, is_numeric($r['claim_minutes']) ? (int) $r['claim_minutes'] : 5)),
            'fallback' => $r['fallback'] === 'leader' ? 'leader' : 'round_robin',
            'off_start' => is_string($r['off_start']) && preg_match('/^\d{2}:\d{2}$/', $r['off_start']) === 1 ? $r['off_start'] : '20:00',
            'off_end' => is_string($r['off_end']) && preg_match('/^\d{2}:\d{2}$/', $r['off_end']) === 1 ? $r['off_end'] : '08:00',
        ];
    }

    /** @param Rules $rules */
    public static function offHours(array $rules, CarbonImmutable $now): bool
    {
        $t = $now->setTimezone(self::TIMEZONE)->format('H:i');

        return $rules['off_start'] > $rules['off_end'] ? ($t >= $rules['off_start'] || $t < $rules['off_end']) : ($t >= $rules['off_start'] && $t < $rules['off_end']);
    }

    private function assign(Organisation $team, RoutedLead $lead, User $agent, string $how, string $status = 'assigned'): RoutedLead
    {
        $contact = $this->leads->add($agent, ['name' => $lead->name, 'email' => $lead->email, 'phone' => $lead->phone],
            (string) __('teams.routing.note', ['team' => $team->name, 'source' => $lead->source, 'message' => $lead->message ?? ''], $agent->preferredLocale()),
            (string) __('teams.routing.draft', ['name' => $lead->name, 'team' => $team->name], $agent->preferredLocale()), false, ['team lead'], 'team');
        $lead->forceFill(['status' => $status, 'assigned_to' => $agent->id, 'assigned_at' => now(), 'how' => $how, 'contact_uuid' => $contact->uuid])->save();
        DB::table('lead_routing_members')->updateOrInsert(['organisation_id' => $team->id, 'user_id' => $agent->id], ['last_assigned_at' => now(), 'updated_at' => now()]);
        $action = match ($how) {
            'claim' => 'team.lead_claim',
            'fallback' => 'team.lead_fallback',
            'leader' => 'team.lead_leader',
            default => 'team.lead_round_robin',
        };
        $this->audit->record($action, 'routed_lead', $lead->uuid, ['agent_id' => $agent->id], $how === 'claim' ? $agent : null, $team->id);

        return $lead;
    }

    private function toLeader(Organisation $team, RoutedLead $lead): RoutedLead
    {
        $leader = $this->memberships->holdersIn(Role::Broker, $team)->first();

        if ($leader === null) {
            $lead->forceFill(['status' => 'leader', 'how' => 'leader'])->save();

            return $lead;
        }

        return $this->assign($team, $lead, $leader, 'leader', 'leader');
    }

    /** @param list<Member> $pool */
    private function next(Organisation $team, array $pool): User
    {
        usort($pool, static fn (array $a, array $b): int => [$a['last_assigned_at'] ?? '', $a['id']] <=> [$b['last_assigned_at'] ?? '', $b['id']]);

        return User::query()->findOrFail($pool[0]['id']);
    }

    /** @return list<Member> */
    private function active(Organisation $team): array
    {
        return array_values(array_filter($this->members($team), static fn (array $m): bool => $m['active']));
    }

    /** @return list<Member> */
    private function members(Organisation $team): array
    {
        $rows = DB::table('lead_routing_members')->where('organisation_id', $team->id)->get()->keyBy('user_id');
        $out = [];

        foreach ($this->memberships->holdersIn(Role::Agent, $team)->sortBy('name') as $agent) {
            $row = $rows->get($agent->id);
            $languages = is_object($row) && is_string($row->languages) ? json_decode($row->languages, true) : null;
            $out[] = [
                'id' => $agent->id, 'name' => (string) $agent->name, 'active' => ! is_object($row) || (bool) $row->active,
                'languages' => is_array($languages) ? array_values(array_filter($languages, 'is_string')) : [$agent->locale->value],
                'last_assigned_at' => is_object($row) && is_string($row->last_assigned_at) ? CarbonImmutable::parse($row->last_assigned_at)->toIso8601String() : null,
            ];
        }

        return $out;
    }

    /** @return LeadRow */
    private function row(RoutedLead $l): array
    {
        return [
            'id' => $l->uuid, 'source' => $l->source, 'name' => $l->name, 'language' => $l->language, 'status' => $l->status, 'how' => $l->how,
            'agent' => $l->assigned_to === null ? null : User::query()->find($l->assigned_to)?->name,
            'claim_by' => $l->claim_by?->toIso8601String(), 'created_at' => $l->created_at?->toIso8601String() ?? '', 'auto_reply' => $l->auto_reply,
        ];
    }

    private static function autoReply(Organisation $team, ?string $language): string
    {
        $text = __('teams.routing.autoReply', ['team' => $team->name], $language === 'es' ? 'es' : 'en');

        return is_string($text) ? $text : '';
    }

    /** The team the person leads. */
    public function team(User $leader): Organisation
    {
        [, $team] = $this->access->scope($leader);

        return $team ?? throw new NotFoundHttpException;
    }
}
