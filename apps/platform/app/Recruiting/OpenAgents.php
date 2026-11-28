<?php

declare(strict_types=1);

namespace App\Recruiting;

use App\Agents\AgentModes;
use App\Authorization\Role;
use App\Messaging\Conversations;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\RecruitingInvite;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Recruit licensed agents (A17.2, ADR 0083; broker screen 27): agents who
 * turned on "Open to recruiting" (Settings › Privacy), licensed in the
 * brokerage's states and not already with it.
 *
 * - The broker sees the first name and initial, closings in 12 months,
 *   the agent's focus (their mode) and why they match.
 * - **Invite to talk** opens a private chat in Messages, once every 90
 *   days. The agent answers there, or blocks.
 * - **Their current broker is never told:** nothing is shown, audited or
 *   sent to the agent's own brokerage.
 *
 * @phpstan-type Row array{id: int, name: string, closings: int, focus: string, reasons: list<'closings'|'city'|'new'>, invited_at: string|null, conversation: string|null}
 */
final readonly class OpenAgents
{
    public const AGAIN_DAYS = 90;

    public function __construct(
        private AgentModes $modes,
        private Conversations $conversations,
        private Tenancy $tenancy,
        private AuditLogger $audit,
    ) {}

    /** @return list<Row> */
    public function list(Organisation $brokerage, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $tree = [$brokerage->id, ...Organisation::query()->where('parent_id', $brokerage->id)->pluck('id')->all()];
        $agentRole = DB::table('roles')->where('name', Role::Agent->value)->value('id');
        $int = static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0;
        $ours = array_map($int, DB::table('model_has_roles')->where('role_id', $agentRole)->whereIn('organisation_id', $tree)->pluck('model_id')->all());
        $open = array_map($int, DB::table('consent_records')->where('purpose', 'recruiting')->whereNull('subject')->orderBy('user_id')->orderByDesc('id')
            ->get(['user_id', 'granted'])->unique('user_id')->filter(static fn (object $r): bool => (bool) $r->granted)->pluck('user_id')->all());
        $ids = Licence::query()->whereIn('user_id', array_diff($open, $ours))->whereIn('state_code', $brokerage->states ?? ['TX'])
            ->whereIn('user_id', DB::table('model_has_roles')->where('role_id', $agentRole)->pluck('model_id'))->distinct()->pluck('user_id');

        if ($ids->isEmpty()) {
            return [];
        }

        $since = $now->subYear();
        $cities = $this->tenancy->asPlatform(static fn () => Deal::query()->whereIn('user_id', $ours)->whereNotNull('city')->limit(2000)->pluck('city')
            ->map(static fn (mixed $c): string => mb_strtolower(is_string($c) ? $c : ''))->unique()->all());
        $invites = RecruitingInvite::query()->where('brokerage_id', $brokerage->id)->whereIn('agent_id', $ids)->latest('id')->get()->keyBy('agent_id');
        $rows = [];

        foreach (User::query()->whereIn('id', $ids)->get() as $agent) {
            $deals = $this->tenancy->asPlatform(static fn () => Deal::query()->where('user_id', $agent->id)->get(['stage', 'closed_at', 'city']));
            $closings = $deals->filter(static fn (Deal $d): bool => $d->stage === 'closed' && $d->closed_at !== null && $d->closed_at->gte($since))->count();
            $first = Licence::query()->where('user_id', $agent->id)->min('licensed_on');
            $reasons = [];

            if ($closings >= 6) {
                $reasons[] = 'closings';
            }

            if ($deals->contains(static fn (Deal $d): bool => $d->city !== null && in_array(mb_strtolower($d->city), $cities, true))) {
                $reasons[] = 'city';
            }

            if (is_string($first) && CarbonImmutable::parse($first)->gte($now->subYears(2))) {
                $reasons[] = 'new';
            }

            $invite = $invites->get($agent->id);
            $rows[] = [
                'id' => $agent->id, 'name' => SponsoredHelp::firstName((string) $agent->name), 'closings' => $closings, 'focus' => $this->modes->goalOf($agent),
                'reasons' => $reasons, 'invited_at' => $invite?->created_at?->toIso8601String(), 'conversation' => null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [count($b['reasons']), $b['closings']] <=> [count($a['reasons']), $a['closings']]);

        return $rows;
    }

    /** Invite to talk: a private chat with the agent, once every 90 days. Returns the conversation's id. */
    public function invite(Organisation $brokerage, User $broker, int $agentId, string $message): string
    {
        if (! in_array($agentId, array_column($this->list($brokerage), 'id'), true)) {
            throw new NotFoundHttpException;
        }

        $recent = RecruitingInvite::query()->where('brokerage_id', $brokerage->id)->where('agent_id', $agentId)->where('created_at', '>=', now()->subDays(self::AGAIN_DAYS))->exists();

        if (! $recent) {
            $invite = RecruitingInvite::query()->create(['uuid' => (string) Str::uuid7(), 'brokerage_id' => $brokerage->id, 'broker_id' => $broker->id, 'agent_id' => $agentId]);
            // Recorded for the inviting brokerage only: the agent's own brokerage is never told.
            $this->audit->record('recruiting.agent_invited', 'recruiting_invite', $invite->uuid, ['agent_id' => $agentId], $broker, $brokerage->id);
        }

        $body = trim($message) !== '' ? trim($message) : __('recruiting.agents.hello', ['brokerage' => $brokerage->name]);

        return $this->conversations->start($broker, [$agentId], is_string($body) ? $body : '')->uuid;
    }
}
