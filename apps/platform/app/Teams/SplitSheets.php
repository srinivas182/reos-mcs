<?php

declare(strict_types=1);

namespace App\Teams;

use App\Authorization\Role;
use App\Messaging\Conversations;
use App\Models\Organisation;
use App\Models\SplitSheet;
use App\Models\User;
use App\Notifications\SplitSheetSent;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Split sheets (A17.3, ADR 0084; team leader screen 30). The team agreement
 * sets the brokerage's and the team's percent; the agent gets the rest.
 * The team leader sends a sheet for a closing; the agent signs it in the
 * app, or asks a question, which opens a chat with the team leader.
 * Payments run through the brokerage's accounting tool (QuickBooks, B17.3).
 *
 * @phpstan-type Agreement array{brokerage_percent: int, team_percent: int, agent_percent: int}
 * @phpstan-type Sheet array{id: string, agent: string, property: string, closing_on: string, lead_source: string|null, gross: int, brokerage: int, team: int, agent_share: int, status: string, signed_at: string|null, exported_at: string|null}
 */
final readonly class SplitSheets
{
    public const DEFAULT_AGREEMENT = ['brokerage_percent' => 20, 'team_percent' => 30];

    public function __construct(
        private LeadRouting $routing,
        private Memberships $memberships,
        private Conversations $conversations,
        private Tenancy $tenancy,
        private AuditLogger $audit,
    ) {}

    /** @return Agreement */
    public static function agreement(Organisation $team): array
    {
        $a = $team->team_agreement ?? [];
        $brokerage = max(0, min(100, is_numeric($a['brokerage_percent'] ?? null) ? (int) $a['brokerage_percent'] : self::DEFAULT_AGREEMENT['brokerage_percent']));
        $teamShare = max(0, min(100 - $brokerage, is_numeric($a['team_percent'] ?? null) ? (int) $a['team_percent'] : self::DEFAULT_AGREEMENT['team_percent']));

        return ['brokerage_percent' => $brokerage, 'team_percent' => $teamShare, 'agent_percent' => 100 - $brokerage - $teamShare];
    }

    /** @return array{team: string, agreement: Agreement, sheets: list<Sheet>, agents: list<array{id: int, name: string}>} */
    public function forLeader(User $leader): array
    {
        $team = $this->routing->team($leader);

        return [
            'team' => $team->name, 'agreement' => self::agreement($team),
            'sheets' => $this->rows(fn () => SplitSheet::query()->where('organisation_id', $team->id)->with('agent')->latest('id')->limit(100)->get(), $team->id),
            'agents' => array_values($this->memberships->holdersIn(Role::Agent, $team)->sortBy('name')->map(static fn (User $u): array => ['id' => $u->id, 'name' => (string) $u->name])->all()),
        ];
    }

    /** @return Agreement */
    public function setAgreement(User $leader, int $brokeragePercent, int $teamPercent): array
    {
        $team = $this->routing->team($leader);

        if ($brokeragePercent + $teamPercent > 100) {
            throw ValidationException::withMessages(['team_percent' => __('teams.splits.errors.over')]);
        }

        $before = self::agreement($team);
        $team->forceFill(['team_agreement' => ['brokerage_percent' => $brokeragePercent, 'team_percent' => $teamPercent]])->save();
        $this->audit->record('team.agreement_changed', 'organisation', (string) $team->id, ['before' => $before, 'after' => self::agreement($team)], $leader, $team->id);

        return self::agreement($team);
    }

    public function create(User $leader, int $agentId, string $property, string $closingOn, int $grossCents, ?string $leadSource): SplitSheet
    {
        $team = $this->routing->team($leader);
        $agent = $this->memberships->holdersIn(Role::Agent, $team)->firstWhere('id', $agentId) ?? throw new NotFoundHttpException;
        $a = self::agreement($team);
        $brokerage = intdiv($grossCents * $a['brokerage_percent'] + 50, 100);
        $teamShare = intdiv($grossCents * $a['team_percent'] + 50, 100);

        $sheet = $this->tenancy->run($team->id, static fn (): SplitSheet => SplitSheet::query()->create([
            'uuid' => (string) Str::uuid7(), 'organisation_id' => $team->id, 'agent_id' => $agent->id, 'created_by' => $leader->id,
            'property' => Str::limit(trim($property), 200, ''), 'closing_on' => $closingOn, 'lead_source' => $leadSource,
            'gross_cents' => $grossCents, 'brokerage_cents' => $brokerage, 'team_cents' => $teamShare, 'agent_cents' => $grossCents - $brokerage - $teamShare, 'status' => 'sent',
        ]));
        $agent->notify(new SplitSheetSent($sheet, $team->name));
        $this->audit->record('team.split_sent', 'split_sheet', $sheet->uuid, ['agent_id' => $agent->id, 'gross_cents' => $grossCents], $leader, $team->id);

        return $sheet;
    }

    /** @return list<Sheet> */
    public function mine(User $agent): array
    {
        return $this->rows(static fn () => SplitSheet::query()->where('agent_id', $agent->id)->with('agent')->latest('id')->limit(50)->get(), null);
    }

    public function sign(User $agent, string $uuid): SplitSheet
    {
        $sheet = $this->own($agent, $uuid);

        if ($sheet->status === 'signed') {
            return $sheet;
        }

        $this->tenancy->run($sheet->organisation_id, static fn () => $sheet->forceFill(['status' => 'signed', 'signed_at' => now()])->save());
        $this->audit->record('team.split_signed', 'split_sheet', $sheet->uuid, ['agent_cents' => $sheet->agent_cents], $agent, $sheet->organisation_id);

        return $sheet;
    }

    /** A question about the sheet: a chat with the team leader. Returns the conversation's id. */
    public function question(User $agent, string $uuid, string $text): string
    {
        $sheet = $this->own($agent, $uuid);

        if ($sheet->status === 'signed') {
            throw ValidationException::withMessages(['sheet' => __('teams.splits.errors.signed')]);
        }

        $leader = User::query()->findOrFail($sheet->created_by);
        $this->tenancy->run($sheet->organisation_id, static fn () => $sheet->forceFill(['status' => 'questioned'])->save());
        $body = __('teams.splits.questionBody', ['property' => $sheet->property, 'text' => trim($text)], $agent->preferredLocale());

        return $this->conversations->start($agent, [$leader->id], is_string($body) ? $body : $text)->uuid;
    }

    private function own(User $agent, string $uuid): SplitSheet
    {
        return $this->tenancy->asPlatform(static fn () => SplitSheet::query()->where('agent_id', $agent->id)->where('uuid', $uuid)->first()) ?? throw new NotFoundHttpException;
    }

    /**
     * @param  \Closure(): Collection<int, SplitSheet>  $query
     * @return list<Sheet>
     */
    private function rows(\Closure $query, ?int $tenant): array
    {
        $sheets = $tenant === null ? $this->tenancy->asPlatform($query) : $this->tenancy->run($tenant, $query);
        $out = [];

        foreach ($sheets as $s) {
            $out[] = [
                'id' => $s->uuid, 'agent' => (string) $s->agent->name, 'property' => $s->property, 'closing_on' => $s->closing_on->toDateString(), 'lead_source' => $s->lead_source,
                'gross' => $s->gross_cents, 'brokerage' => $s->brokerage_cents, 'team' => $s->team_cents, 'agent_share' => $s->agent_cents, 'status' => $s->status,
                'signed_at' => $s->signed_at?->toIso8601String(), 'exported_at' => $s->exported_at?->toIso8601String(),
            ];
        }

        return $out;
    }
}
