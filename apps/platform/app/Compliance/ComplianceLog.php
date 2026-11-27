<?php

declare(strict_types=1);

namespace App\Compliance;

use App\Models\ComplianceEvent;
use App\Models\Organisation;
use App\Models\User;
use App\Studio\AdvertisingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The compliance log (B13.2, ADR 0058): every check that decided whether
 * something went out, every fix, every request to the broker, approval and
 * send-back, on every channel, with who did it and when. The broker reads
 * it filtered by agent, channel and action, and exports it to CSV.
 *
 * Checks while typing aren't logged; checks at the moment of sending are.
 *
 * @phpstan-import-type Issue from ComplianceEngine
 *
 * @phpstan-type Filters array{agent?: int|null, channel?: string|null, action?: string|null, from?: string|null, to?: string|null}
 * @phpstan-type EventRow array{id: int, at: string, agent: string, actor: string|null, channel: string, action: string, subject: string|null, issues: list<array{code: string, phrase: string|null, citation: string}>, note: string|null}
 */
final readonly class ComplianceLog
{
    public const PAGE = 100;

    public function __construct(private AdvertisingPolicy $policy) {}

    /**
     * @param  list<Issue>  $issues
     */
    public function record(User $agent, string $channel, string $action, array $issues = [], ?User $actor = null, ?string $subjectType = null, ?string $subjectId = null, ?string $note = null): ComplianceEvent
    {
        return ComplianceEvent::query()->create([
            'organisation_id' => $this->policy->brokerage($agent)?->id,
            'user_id' => $agent->id,
            'actor_id' => ($actor ?? $agent)->id,
            'channel' => $channel,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'issues' => array_map(static fn (array $i): array => ['code' => $i['code'], 'phrase' => $i['phrase'], 'citation' => $i['citation']], $issues),
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * The brokerage's log, newest first.
     *
     * @param  Filters  $filters
     * @return list<EventRow>
     */
    public function list(Organisation $brokerage, array $filters, int $limit = self::PAGE): array
    {
        return array_values($this->query($brokerage, $filters)->with(['user', 'actor'])->orderByDesc('id')->limit($limit)->get()
            ->map(fn (ComplianceEvent $e): array => $this->row($e))->all());
    }

    /**
     * Every matching line, for the CSV export.
     *
     * @param  Filters  $filters
     * @return iterable<EventRow>
     */
    public function export(Organisation $brokerage, array $filters): iterable
    {
        foreach ($this->query($brokerage, $filters)->with(['user', 'actor'])->orderByDesc('id')->lazyById(500) as $event) {
            yield $this->row($event);
        }
    }

    /**
     * @param  Filters  $filters
     * @return Builder<ComplianceEvent>
     */
    private function query(Organisation $brokerage, array $filters): Builder
    {
        $agent = $filters['agent'] ?? null;
        $channel = $filters['channel'] ?? null;
        $action = $filters['action'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        return ComplianceEvent::query()->where('organisation_id', $brokerage->id)
            ->when($agent !== null, static fn (Builder $q) => $q->where('user_id', $agent))
            ->when($channel !== null, static fn (Builder $q) => $q->where('channel', $channel))
            ->when($action !== null, static fn (Builder $q) => $q->where('action', $action))
            ->when($from !== null, static fn (Builder $q) => $q->where('created_at', '>=', CarbonImmutable::parse((string) $from)->startOfDay()))
            ->when($to !== null, static fn (Builder $q) => $q->where('created_at', '<=', CarbonImmutable::parse((string) $to)->endOfDay()));
    }

    /** @return EventRow */
    private function row(ComplianceEvent $e): array
    {
        return [
            'id' => $e->id, 'at' => $e->created_at->toIso8601String(), 'agent' => (string) $e->user->name, 'actor' => $e->actor === null ? null : (string) $e->actor->name,
            'channel' => $e->channel, 'action' => $e->action, 'subject' => $e->subject_id, 'issues' => $e->issues, 'note' => $e->note,
        ];
    }
}
