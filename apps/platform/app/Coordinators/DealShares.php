<?php

declare(strict_types=1);

namespace App\Coordinators;

use App\Messaging\Conversations;
use App\Models\Deal;
use App\Models\DealShare;
use App\Models\ProviderRequest;
use App\Models\User;
use App\Notifications\DealShared;
use App\Services\Audit\AuditLogger;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Coordinators (B17.1, ADR 0085; provider screens 14–15, agent "share with
 * a coordinator").
 *
 * - **Who:** an agent shares their own deal with a transaction coordinator
 *   they're connected with (B16), never anyone else.
 * - **Switches:** dates, documents, parties and messages; documents and
 *   dates on by default (decision 4). The coordinator sees only what's on.
 * - **Access ends at closing** (or when the deal is cancelled, or the agent
 *   removes the coordinator): at once when the stage changes (Deal saved),
 *   with a daily sweep as a backstop. After that the coordinator sees
 *   nothing of the deal.
 * - **My files:** the coordinator's board of active files, the next
 *   deadline, missing documents and progress; ticking an item shows the
 *   agent; a drafted message goes to the agent's chat with them, so the
 *   agent is copied. Every action is audited for the deal's brokerage.
 * - Coordinators handle paperwork and scheduling; negotiation and advice
 *   stay with the licensed agent.
 *
 * @phpstan-type Switches array{dates: bool, documents: bool, parties: bool, messages: bool}
 * @phpstan-type FileCard array{id: string, agent: string, property: string, stage: string, shared_on: string, next: array{label: string, due_on: string}|null, missing: int, progress: array{done: int, total: int}}
 * @phpstan-type FileDetail array{id: string, agent: string, property: string, city: string|null, stage: string, shared_on: string, switches: Switches, dates: list<array{id: string, label: string, due_on: string, done: bool}>|null, documents: list<array{name: string, status: string, done: bool}>|null, parties: list<array{name: string, role: string}>|null, draft: string, conversation: string|null}
 */
final readonly class DealShares
{
    public function __construct(private Tenancy $tenancy, private Conversations $conversations, private AuditLogger $audit) {}

    /**
     * Coordinators the agent can share with: connected, in the coordinator category.
     *
     * @return list<array{id: int, name: string, business: string}>
     */
    public function coordinatorsFor(User $agent): array
    {
        $out = [];

        foreach (ProviderRequest::query()->where('agent_id', $agent->id)->where('status', ProviderRequest::ACCEPTED)
            ->whereHas('profile.category', static fn ($q) => $q->where('key', 'transaction-coordinator'))->with(['provider', 'profile'])->get() as $r) {
            $out[] = ['id' => $r->provider_id, 'name' => (string) $r->provider->name, 'business' => (string) ($r->profile->business_name ?? $r->provider->name)];
        }

        return $out;
    }

    /**
     * The deal's shares, for its agent.
     *
     * @return array{coordinators: list<array{id: int, name: string, business: string}>, shares: list<array{id: string, coordinator: string, switches: Switches, status: string, end_reason: string|null, ended_at: string|null}>}
     */
    public function forDeal(User $agent, string $dealUuid): array
    {
        $deal = $this->ownDeal($agent, $dealUuid);
        $shares = [];

        foreach (DealShare::query()->where('deal_id', $deal->id)->with('coordinator')->latest('id')->get() as $s) {
            $shares[] = ['id' => $s->uuid, 'coordinator' => (string) $s->coordinator->name, 'switches' => self::switches($s), 'status' => $s->status, 'end_reason' => $s->end_reason, 'ended_at' => $s->ended_at?->toIso8601String()];
        }

        return ['coordinators' => $this->coordinatorsFor($agent), 'shares' => $shares];
    }

    /** @param array<string, bool> $switches */
    public function share(User $agent, string $dealUuid, int $coordinatorId, array $switches): DealShare
    {
        $deal = $this->ownDeal($agent, $dealUuid);

        if (! in_array($coordinatorId, array_column($this->coordinatorsFor($agent), 'id'), true)) {
            throw ValidationException::withMessages(['coordinator_id' => __('coordinators.errors.notConnected')]);
        }

        if (in_array($deal->stage, ['closed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['deal' => __('coordinators.errors.closed')]);
        }

        $share = DealShare::query()->where('deal_id', $deal->id)->where('coordinator_id', $coordinatorId)->where('status', 'active')->first()
            ?? DealShare::query()->create(['uuid' => (string) Str::uuid7(), 'deal_id' => $deal->id, 'agent_id' => $agent->id, 'coordinator_id' => $coordinatorId, 'switches' => DealShare::DEFAULTS, 'status' => 'active']);
        $share->forceFill(['switches' => self::clean($switches)])->save();
        User::query()->findOrFail($coordinatorId)->notify(new DealShared($share->load('deal', 'agent')));
        $this->record('coordinator.shared', $share->uuid, ['coordinator_id' => $coordinatorId, 'switches' => $share->switches], $agent, $deal->organisation_id);

        return $share;
    }

    /** @param array<string, bool> $switches */
    public function setSwitches(User $agent, string $uuid, array $switches): DealShare
    {
        $share = DealShare::query()->where('agent_id', $agent->id)->where('uuid', $uuid)->where('status', 'active')->with('deal')->first() ?? throw new NotFoundHttpException;
        $before = self::switches($share);
        $share->forceFill(['switches' => self::clean($switches)])->save();
        $this->record('coordinator.switches', $share->uuid, ['before' => $before, 'after' => $share->switches], $agent, $share->deal->organisation_id);

        return $share;
    }

    public function remove(User $agent, string $uuid): void
    {
        $share = DealShare::query()->where('agent_id', $agent->id)->where('uuid', $uuid)->where('status', 'active')->with('deal')->first() ?? throw new NotFoundHttpException;
        $this->end($share, 'removed', $agent);
    }

    /** The deal closed or was cancelled: every active share ends (called when a deal is saved). */
    public function dealChanged(Deal $deal): void
    {
        if (! in_array($deal->stage, ['closed', 'cancelled'], true)) {
            return;
        }

        foreach (DealShare::query()->where('deal_id', $deal->id)->where('status', 'active')->with('deal')->get() as $share) {
            $this->end($share, $deal->stage === 'closed' ? 'closed' : 'cancelled', null);
        }
    }

    /** Daily backstop: shares on deals that closed some other way. Returns how many ended. */
    public function sweep(): int
    {
        $count = 0;

        foreach (DealShare::query()->where('status', 'active')->with('deal')->get() as $share) {
            $deal = $this->tenancy->asPlatform(static fn () => Deal::query()->find($share->deal_id));

            if ($deal === null || in_array($deal->stage, ['closed', 'cancelled'], true)) {
                $this->end($share, $deal?->stage === 'cancelled' ? 'cancelled' : 'closed', null);
                $count++;
            }
        }

        return $count;
    }

    /**
     * My files (coordinator): active files, nearest deadline first.
     *
     * @return array{counts: array{active: int, due_this_week: int, missing: int}, files: list<FileCard>}
     */
    public function board(User $coordinator, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $cards = [];

        foreach (DealShare::query()->where('coordinator_id', $coordinator->id)->where('status', 'active')->with('agent')->get() as $share) {
            $deal = $this->dealOf($share);
            $sw = self::switches($share);
            $dates = $sw['dates'] ? $deal->dates->sortBy('due_on')->values() : collect();
            $next = $dates->first(static fn ($d): bool => $d->done_at === null && ! in_array((string) $d->id, $share->checklist ?? [], true));
            $docs = $sw['documents'] ? ($deal->documents ?? []) : [];
            $missing = count(array_filter($docs, static fn (array $d): bool => $d['status'] === 'missing' && ! in_array('doc:'.$d['name'], $share->checklist ?? [], true)));
            $total = count($docs) + $dates->count();
            $cards[] = [
                'id' => $share->uuid, 'agent' => (string) $share->agent->name, 'property' => $deal->property, 'stage' => $deal->stage, 'shared_on' => $share->created_at?->toDateString() ?? '',
                'next' => $next === null ? null : ['label' => $next->label ?? $next->kind, 'due_on' => $next->due_on->toDateString()],
                'missing' => $missing, 'progress' => ['done' => min($total, count($share->checklist ?? []) + $dates->whereNotNull('done_at')->count()), 'total' => $total],
            ];
        }

        usort($cards, static fn (array $a, array $b): int => [$a['next']['due_on'] ?? '9999'] <=> [$b['next']['due_on'] ?? '9999']);
        $week = $today->addDays(7)->toDateString();

        return [
            'counts' => ['active' => count($cards), 'due_this_week' => count(array_filter($cards, static fn (array $c): bool => $c['next'] !== null && $c['next']['due_on'] <= $week)), 'missing' => array_sum(array_column($cards, 'missing'))],
            'files' => $cards,
        ];
    }

    /** @return FileDetail */
    public function file(User $coordinator, string $uuid): array
    {
        $share = $this->activeFor($coordinator, $uuid);
        $deal = $this->dealOf($share);
        $sw = self::switches($share);
        $done = $share->checklist ?? [];
        $missing = array_values(array_map(static fn (array $d): string => $d['name'], array_filter($deal->documents ?? [], static fn (array $d): bool => $d['status'] === 'missing' && ! in_array('doc:'.$d['name'], $done, true))));
        $next = $deal->dates->sortBy('due_on')->first(static fn ($d): bool => $d->done_at === null);
        $draft = __('coordinators.draft', [
            'coordinator' => (string) $coordinator->name, 'agent' => (string) $share->agent->name, 'property' => $deal->property,
            'missing' => $missing === [] || ! $sw['documents'] ? __('coordinators.nothingMissing') : implode(', ', $missing),
            'next' => $next === null || ! $sw['dates'] ? '—' : ($next->label ?? $next->kind).' '.$next->due_on->toDateString(),
        ]);

        return [
            'id' => $share->uuid, 'agent' => (string) $share->agent->name, 'property' => $deal->property, 'city' => $deal->city, 'stage' => $deal->stage,
            'shared_on' => $share->created_at?->toDateString() ?? '', 'switches' => $sw,
            'dates' => $sw['dates'] ? array_values($deal->dates->sortBy('due_on')->map(static fn ($d): array => ['id' => (string) $d->id, 'label' => (string) ($d->label ?? $d->kind), 'due_on' => $d->due_on->toDateString(), 'done' => $d->done_at !== null || in_array((string) $d->id, $done, true)])->all()) : null,
            'documents' => $sw['documents'] ? (array_map(static fn (array $d): array => ['name' => $d['name'], 'status' => $d['status'], 'done' => $d['status'] === 'complete' || in_array('doc:'.$d['name'], $done, true)], $deal->documents ?? [])) : null,
            'parties' => $sw['parties'] ? (array_map(static fn (array $p): array => ['name' => $p['name'], 'role' => $p['role']], $deal->parties)) : null,
            'draft' => is_string($draft) ? $draft : '', 'conversation' => null,
        ];
    }

    /** Ticks an item (a document name as `doc:<name>`, or a date's id); the agent sees progress. */
    public function tick(User $coordinator, string $uuid, string $item, bool $done): void
    {
        $share = $this->activeFor($coordinator, $uuid);
        $list = $share->checklist ?? [];
        $list = $done ? array_values(array_unique([...$list, $item])) : array_values(array_diff($list, [$item]));
        $share->forceFill(['checklist' => $list])->save();
        $this->record('coordinator.ticked', $share->uuid, ['item' => $item, 'done' => $done], $coordinator, $this->dealOf($share)->organisation_id);
    }

    /** Sends a message about the file to the agent (the agent is always copied). Needs the messages switch. */
    public function message(User $coordinator, string $uuid, string $body): string
    {
        $share = $this->activeFor($coordinator, $uuid);

        if (! self::switches($share)['messages']) {
            throw ValidationException::withMessages(['body' => __('coordinators.errors.messagesOff')]);
        }

        $conversation = $this->conversations->start($coordinator, [$share->agent_id], trim($body));
        $this->record('coordinator.messaged', $share->uuid, [], $coordinator, $this->dealOf($share)->organisation_id);

        return $conversation->uuid;
    }

    /** @return Switches */
    public static function switches(DealShare $share): array
    {
        return self::clean($share->switches);
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return Switches
     */
    private static function clean(array $input): array
    {
        $out = DealShare::DEFAULTS;

        foreach (DealShare::SWITCHES as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = (bool) $input[$key];
            }
        }

        return $out;
    }

    private function end(DealShare $share, string $reason, ?User $actor): void
    {
        $share->forceFill(['status' => 'ended', 'ended_at' => now(), 'end_reason' => $reason])->save();
        $this->record('coordinator.access_ended', $share->uuid, ['reason' => $reason], $actor, $this->dealOf($share)->organisation_id);
    }

    /**
     * Audited for the deal's brokerage (B17.1). A coordinator works outside
     * that tenant, so the event is written at platform level.
     *
     * @param  array<string, mixed>  $properties
     */
    private function record(string $action, string $uuid, array $properties, ?User $actor, ?int $organisationId): void
    {
        $this->tenancy->asPlatform(fn () => $this->audit->record($action, 'deal_share', $uuid, $properties, $actor, $organisationId));
    }

    private function activeFor(User $coordinator, string $uuid): DealShare
    {
        $share = DealShare::query()->where('coordinator_id', $coordinator->id)->where('uuid', $uuid)->where('status', 'active')->with('agent')->first() ?? throw new NotFoundHttpException;
        $deal = $this->dealOf($share);

        // Closed some other way since: access ends now.
        if (in_array($deal->stage, ['closed', 'cancelled'], true)) {
            $this->end($share, $deal->stage === 'closed' ? 'closed' : 'cancelled', null);

            throw new NotFoundHttpException;
        }

        return $share;
    }

    private function dealOf(DealShare $share): Deal
    {
        return $this->tenancy->asPlatform(static fn () => Deal::query()->with('dates')->find($share->deal_id)) ?? throw new NotFoundHttpException;
    }

    private function ownDeal(User $agent, string $uuid): Deal
    {
        return $this->tenancy->asPlatform(static fn () => Deal::query()->where('user_id', $agent->id)->where('uuid', $uuid)->first()) ?? throw new NotFoundHttpException;
    }
}
