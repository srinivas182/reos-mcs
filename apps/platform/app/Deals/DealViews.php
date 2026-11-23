<?php

declare(strict_types=1);

namespace App\Deals;

use App\Models\Deal;
use App\Models\DealDate;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What the deal screens show (B9.1): the web Deals board (screen 14), the
 * phone's Deals pipeline (Agent App screen 9) and Deal alerts (screen 10),
 * and one deal.
 *
 * @phpstan-type DateRow array{id: int, kind: string, name: string, due_on: string, days: int, done: bool}
 * @phpstan-type Card array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: DateRow|null, documents: array{complete: int, total: int, missing: int}}
 * @phpstan-type Full array{id: int, uuid: string, property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, source: string, read_only: bool, external_url: string|null, next: DateRow|null, documents: array{complete: int, total: int, missing: int}, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, files: list<array{name: string, status: string, url: string|null}>, dates: list<DateRow>}
 */
final readonly class DealViews
{
    /** Alerts look this many days ahead. */
    public const ALERT_DAYS = 14;

    /**
     * The board: open deals by stage, closed ones from the last 90 days.
     *
     * @return array<string, list<Card>>
     */
    public function board(User $user): array
    {
        $deals = Deal::query()->where('user_id', $user->id)->with('dates')
            ->where(static fn ($q) => $q->whereIn('stage', ['active', 'under_contract'])->orWhere(static fn ($c) => $c->where('stage', 'closed')->where('closed_at', '>=', now()->subDays(90))))
            ->orderBy('property')->get();
        $board = ['active' => [], 'under_contract' => [], 'closed' => []];

        foreach ($deals as $deal) {
            $board[$deal->stage][] = $this->card($deal, $user);
        }

        foreach ($board as $stage => $cards) {
            usort($cards, static fn (array $a, array $b): int => [$a['next']['due_on'] ?? '9999', $a['property']] <=> [$b['next']['due_on'] ?? '9999', $b['property']]);
            $board[$stage] = $cards;
        }

        return $board;
    }

    /**
     * The pipeline (phone screen 9): under contract, active clients and pending commission.
     *
     * @return array{under_contract: int, active: int, pending_commission: string, closed_this_year: int, deals: list<Card>}
     */
    public function pipeline(User $user): array
    {
        $open = Deal::query()->where('user_id', $user->id)->whereIn('stage', ['active', 'under_contract'])->with('dates')->get();
        $cards = array_values($open->map(fn (Deal $d): array => $this->card($d, $user))->all());
        usort($cards, static fn (array $a, array $b): int => [$a['stage'] === 'under_contract' ? 0 : 1, $a['next']['due_on'] ?? '9999'] <=> [$b['stage'] === 'under_contract' ? 0 : 1, $b['next']['due_on'] ?? '9999']);
        $pending = $open->where('stage', 'under_contract')->sum(static fn (Deal $d): float => (float) ($d->commission ?? 0));

        return [
            'under_contract' => $open->where('stage', 'under_contract')->count(),
            'active' => $open->where('stage', 'active')->count(),
            'pending_commission' => number_format($pending, 2, '.', ''),
            'closed_this_year' => Deal::query()->where('user_id', $user->id)->where('stage', 'closed')->where('closed_at', '>=', CarbonImmutable::now()->startOfYear())->count(),
            'deals' => $cards,
        ];
    }

    /**
     * Deal alerts (phone screen 10): deadlines in the next two weeks, then
     * deals missing documents.
     *
     * @return array{deadlines: list<array{deal: Card, date: DateRow}>, missing: list<array{deal: Card, documents: list<string>}>}
     */
    public function alerts(User $user): array
    {
        $deals = Deal::query()->where('user_id', $user->id)->whereIn('stage', ['active', 'under_contract'])->with('dates')->get();
        $today = Deadlines::today($user);
        $deadlines = [];
        $missing = [];

        foreach ($deals as $deal) {
            $card = $this->card($deal, $user);
            foreach ($deal->dates as $date) {
                $days = self::days($today, $date);
                if ($date->done_at === null && $days >= 0 && $days <= self::ALERT_DAYS) {
                    $deadlines[] = ['deal' => $card, 'date' => self::date($date, $today, $user)];
                }
            }
            $names = array_values(array_map(static fn (array $d): string => $d['name'], array_filter($deal->documents ?? [], static fn (array $d): bool => $d['status'] === 'missing')));
            if ($names !== []) {
                $missing[] = ['deal' => $card, 'documents' => $names];
            }
        }

        usort($deadlines, static fn (array $a, array $b): int => $a['date']['due_on'] <=> $b['date']['due_on']);

        return ['deadlines' => $deadlines, 'missing' => $missing];
    }

    /** @return Full */
    public function show(Deal $deal, User $user): array
    {
        $today = Deadlines::today($user);

        return $this->card($deal, $user) + [
            'parties' => $deal->parties ?? [],
            'files' => $deal->documents ?? [],
            'dates' => array_values($deal->dates->map(static fn (DealDate $d): array => self::date($d, $today, $user))->all()),
        ];
    }

    /** @return Card */
    public function card(Deal $deal, User $user): array
    {
        $today = Deadlines::today($user);
        $next = $deal->dates->first(static fn (DealDate $d): bool => $d->done_at === null && $d->due_on->toDateString() >= $today->toDateString());
        $documents = $deal->documents ?? [];

        return [
            'id' => $deal->id, 'uuid' => $deal->uuid, 'property' => $deal->property, 'city' => $deal->city, 'side' => $deal->side, 'stage' => $deal->stage,
            'price' => $deal->price, 'commission' => $deal->commission, 'source' => $deal->source, 'read_only' => $deal->readOnly(), 'external_url' => $deal->external_url,
            'next' => $next === null ? null : self::date($next, $today, $user),
            'documents' => [
                'complete' => count(array_filter($documents, static fn (array $d): bool => $d['status'] === 'complete')),
                'total' => count($documents),
                'missing' => count(array_filter($documents, static fn (array $d): bool => $d['status'] === 'missing')),
            ],
        ];
    }

    /** @return DateRow */
    private static function date(DealDate $date, CarbonImmutable $today, User $user): array
    {
        return [
            'id' => $date->id, 'kind' => $date->kind, 'name' => Deals::dateName($date, $user), 'due_on' => $date->due_on->toDateString(),
            'days' => self::days($today, $date), 'done' => $date->done_at !== null,
        ];
    }

    /** Whole days from today (in the agent's time zone) to the date; negative once past. */
    private static function days(CarbonImmutable $today, DealDate $date): int
    {
        return (int) CarbonImmutable::parse($today->toDateString())->diffInDays(CarbonImmutable::parse($date->due_on->toDateString()), false);
    }
}
