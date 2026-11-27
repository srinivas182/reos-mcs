<?php

declare(strict_types=1);

namespace App\Pods;

use App\Agents\Activity;
use App\Authorization\Role;
use App\Mentoring\MentorDirectory;
use App\Models\BrokerageChallenge;
use App\Models\ChallengeEntry;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Brokerage challenges with opt-in leaderboards (A13.2, ADR 0054).
 *
 * A broker sets a challenge on one of the agent's own numbers: most
 * conversations, appointments, CE hours or posts between two dates.
 * Agents choose to join; only those who join appear on the boards. There
 * are two boards, so a new agent can win too: **total**, and **most
 * improved** against the same length of time just before they joined
 * (their baseline). Beating the baseline is a personal best. Leaving is
 * undoable. Counts come from activity (never typed in), and nothing about
 * choosing a service provider ever counts.
 *
 * @phpstan-type BoardRow array{id: int, name: string, me: bool, value: float|int, improved: float|int, personal_best: bool}
 * @phpstan-type ChallengeRow array{id: string, title: string, metric: string, starts_on: string, ends_on: string, joined: bool, open: bool, entrants: int, total: list<BoardRow>, improved: list<BoardRow>}
 */
final readonly class BrokerageChallenges
{
    public const BOARD = 10;

    public function __construct(private MentorDirectory $directory, private AuditLogger $audit) {}

    /** A broker's new challenge (audited). */
    public function create(User $broker, string $title, string $metric, string $startsOn, string $endsOn): BrokerageChallenge
    {
        $brokerage = $this->directory->brokerageOf($broker, Role::Broker) ?? throw new NotFoundHttpException;
        $starts = CarbonImmutable::parse($startsOn);
        $ends = CarbonImmutable::parse($endsOn);

        if (! in_array($metric, Activity::METRICS, true) || $ends->lt($starts) || $ends->lt(CarbonImmutable::today()) || $starts->diffInDays($ends) > 92) {
            throw ValidationException::withMessages(['ends_on' => __('pods.challenges.errors.dates')]);
        }

        $challenge = BrokerageChallenge::query()->create(['uuid' => (string) Str::uuid7(), 'organisation_id' => $brokerage->id, 'title' => mb_substr(trim($title), 0, 120), 'metric' => $metric, 'starts_on' => $starts->toDateString(), 'ends_on' => $ends->toDateString(), 'created_by' => $broker->id]);
        $this->audit->record('challenge.created', 'brokerage_challenge', $challenge->uuid, ['title' => $challenge->title, 'metric' => $metric, 'starts_on' => $challenge->starts_on->toDateString(), 'ends_on' => $challenge->ends_on->toDateString()], $broker, $brokerage->id);

        return $challenge;
    }

    /**
     * The brokerage's challenges that haven't ended long ago, with their boards.
     *
     * @return list<ChallengeRow>
     */
    public function list(User $viewer, ?Organisation $brokerage = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $brokerage ??= $this->directory->brokerageOf($viewer) ?? $this->directory->brokerageOf($viewer, Role::Broker);

        if ($brokerage === null) {
            return [];
        }

        $rows = [];

        foreach (BrokerageChallenge::query()->where('organisation_id', $brokerage->id)->where('ends_on', '>=', $now->subDays(14)->toDateString())->with('entries.user')->orderBy('ends_on')->get() as $challenge) {
            $rows[] = $this->row($challenge, $viewer, $now);
        }

        return $rows;
    }

    /** Joins a challenge (opt-in); the baseline is the same length of time just before. */
    public function join(User $agent, string $uuid, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $challenge = $this->find($agent, $uuid);

        if ($challenge->ends_on->lt($now->startOfDay())) {
            throw ValidationException::withMessages(['challenge' => __('pods.challenges.errors.ended')]);
        }

        $days = $challenge->starts_on->diffInDays($challenge->ends_on) + 1;
        $baseline = Activity::value($challenge->metric, $agent, $challenge->starts_on->subDays((int) $days)->startOfDay(), $challenge->starts_on->startOfDay());
        $entry = ChallengeEntry::query()->firstOrNew(['brokerage_challenge_id' => $challenge->id, 'user_id' => $agent->id]);
        $entry->forceFill(['baseline' => (string) $baseline, 'left_at' => null])->save();
    }

    /** Leaves a challenge's boards (Undo joins again). */
    public function leave(User $agent, string $uuid): void
    {
        $challenge = $this->find($agent, $uuid);
        ChallengeEntry::query()->where('brokerage_challenge_id', $challenge->id)->where('user_id', $agent->id)->update(['left_at' => now()]);
    }

    /** @return ChallengeRow */
    private function row(BrokerageChallenge $challenge, User $viewer, CarbonImmutable $now): array
    {
        $from = $challenge->starts_on->startOfDay();
        $to = $challenge->ends_on->endOfDay();
        $board = [];

        foreach ($challenge->entries as $entry) {
            $value = Activity::value($challenge->metric, $entry->user, $from, min($to, $now));
            $baseline = (float) $entry->baseline;
            $board[] = [
                'id' => $entry->user_id, 'name' => (string) $entry->user->name, 'me' => $entry->user_id === $viewer->id,
                'value' => self::number($value), 'improved' => self::number($value - $baseline), 'personal_best' => $value > $baseline,
            ];
        }

        $total = $board;
        usort($total, static fn (array $a, array $b): int => [$b['value'], $a['name']] <=> [$a['value'], $b['name']]);
        $improved = $board;
        usort($improved, static fn (array $a, array $b): int => [$b['improved'], $a['name']] <=> [$a['improved'], $b['name']]);

        return [
            'id' => $challenge->uuid, 'title' => $challenge->title, 'metric' => $challenge->metric,
            'starts_on' => $challenge->starts_on->toDateString(), 'ends_on' => $challenge->ends_on->toDateString(),
            'joined' => $challenge->entries->contains('user_id', $viewer->id), 'open' => $challenge->ends_on->gte($now->startOfDay()), 'entrants' => count($board),
            'total' => array_slice($total, 0, self::BOARD), 'improved' => array_slice($improved, 0, self::BOARD),
        ];
    }

    private function find(User $agent, string $uuid): BrokerageChallenge
    {
        $brokerage = $this->directory->brokerageOf($agent) ?? throw new NotFoundHttpException;

        return BrokerageChallenge::query()->where('organisation_id', $brokerage->id)->where('uuid', $uuid)->firstOrFail();
    }

    private static function number(float $value): float|int
    {
        return floor($value) === $value ? (int) $value : round($value, 1);
    }
}
