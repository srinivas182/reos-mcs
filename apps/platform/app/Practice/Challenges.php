<?php

declare(strict_types=1);

namespace App\Practice;

use App\Models\Challenge;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\ChallengeFinished;
use App\Services\States\PersonStates;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Challenges (A9.3b, candidate screen 52). "Challenge a friend" picks 10
 * questions and makes a share link; whoever accepts answers the same 10.
 * Results come at the end; a hint (one wrong option struck out) costs
 * points here only. Everyone who took part sees the board, with first
 * names only, and hears when someone finishes.
 *
 * @phpstan-type Entry array{first_name: string, correct: int, total: int, hints: int, points: int, mine: bool, finished: bool}
 * @phpstan-type Board array{uuid: string, link: string, from: string, mine: bool, questions: int, expires_at: string, expired: bool, attempt_id: int|null, submitted: bool, entries: list<Entry>}
 */
final readonly class Challenges
{
    public const QUESTIONS = 10;

    /** A link works for a week. */
    public const DAYS = 7;

    public const POINTS_RIGHT = 10;

    public const HINT_COST = 5;

    public function __construct(private PersonStates $states) {}

    /** Challenge a friend: 10 questions from the candidate's state, and their own attempt at them. */
    public function create(User $user, ?int $groupId = null): Challenge
    {
        $state = $this->states->current($user);
        $ids = QuizQuestion::query()->whereNull('retired_at')
            ->where(static fn ($q) => $q->where('state_code', $state)->orWhereNull('state_code'))
            ->pluck('id')->shuffle()->take(self::QUESTIONS)->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->values()->all();

        if (count($ids) < self::QUESTIONS) {
            throw ValidationException::withMessages(['challenge' => __('practice.errors.noQuestions')]);
        }

        $challenge = Challenge::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'study_group_id' => $groupId, 'state_code' => $state,
            'question_ids' => $ids, 'expires_at' => CarbonImmutable::now()->addDays(self::DAYS),
        ]);
        $this->attempt($user, $challenge);

        return $challenge;
    }

    /**
     * Accept: the same 10 questions. Accepting again carries on with the
     * same attempt.
     *
     * @throws ValidationException once it has expired
     */
    public function accept(User $user, Challenge $challenge): TestAttempt
    {
        $existing = $challenge->attempts()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($challenge->expires_at->isPast()) {
            throw ValidationException::withMessages(['challenge' => __('practice.challenges.errors.expired')]);
        }

        return $this->attempt($user, $challenge);
    }

    /** Points: 10 a right answer, less 5 for each hint taken. */
    public static function points(TestAttempt $attempt): int
    {
        return max(0, self::POINTS_RIGHT * (int) $attempt->correct - self::HINT_COST * count($attempt->hints ?? []));
    }

    /** Someone finished: everyone else who took part hears their result. */
    public function finished(TestAttempt $attempt): void
    {
        $challenge = $attempt->challenge;

        if ($challenge === null) {
            return;
        }

        foreach ($challenge->attempts()->with('user')->where('user_id', '!=', $attempt->user_id)->get() as $other) {
            $other->user->notify(new ChallengeFinished($challenge->uuid, self::firstName($attempt->user), self::points($attempt), (int) $attempt->correct, $attempt->total));
        }
    }

    /** Whether this person may see the challenge's board: they sent it or accepted it. */
    public function takesPart(User $user, Challenge $challenge): bool
    {
        return $challenge->user_id === $user->id || $challenge->attempts()->where('user_id', $user->id)->exists();
    }

    /**
     * The board: who has answered, by points, with first names only.
     *
     * @return Board
     */
    public function board(Challenge $challenge, User $viewer): array
    {
        $attempts = $challenge->attempts()->with('user')->get();
        $mine = $attempts->first(static fn (TestAttempt $a): bool => $a->user_id === $viewer->id);
        $entries = array_values($attempts->map(static fn (TestAttempt $a): array => [
            'first_name' => self::firstName($a->user),
            'correct' => $a->submitted_at === null ? 0 : (int) $a->correct,
            'total' => $a->total,
            'hints' => count($a->hints ?? []),
            'points' => $a->submitted_at === null ? 0 : self::points($a),
            'mine' => $a->user_id === $viewer->id,
            'finished' => $a->submitted_at !== null,
        ])->sortBy([['finished', 'desc'], ['points', 'desc'], ['first_name', 'asc']])->all());

        return [
            'uuid' => $challenge->uuid,
            'link' => route('challenges.show', $challenge->uuid),
            'from' => self::firstName($challenge->user),
            'mine' => $challenge->user_id === $viewer->id,
            'questions' => count($challenge->question_ids),
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'expired' => $challenge->expires_at->isPast(),
            'attempt_id' => $mine?->id,
            'submitted' => $mine?->submitted_at !== null,
            'entries' => $entries,
        ];
    }

    /**
     * The candidate's challenges, newest first: the ones they sent and the
     * ones they accepted.
     *
     * @return list<Board>
     */
    public function mine(User $user): array
    {
        return array_values(Challenge::query()
            ->where(static fn ($q) => $q->where('user_id', $user->id)->orWhereHas('attempts', static fn ($a) => $a->where('user_id', $user->id)))
            ->with('user')->latest('id')->limit(20)->get()
            ->map(fn (Challenge $c): array => $this->board($c, $user))->all());
    }

    public static function firstName(User $user): string
    {
        return Str::before(trim((string) $user->name), ' ');
    }

    private function attempt(User $user, Challenge $challenge): TestAttempt
    {
        try {
            return TestAttempt::query()->create([
                'user_id' => $user->id, 'kind' => TestAttempt::CHALLENGE, 'mode' => 'exam', 'state_code' => $challenge->state_code, 'topic' => null,
                'challenge_id' => $challenge->id, 'question_ids' => $challenge->question_ids, 'answers' => [], 'flags' => [], 'hints' => [],
                'started_at' => CarbonImmutable::now(), 'ends_at' => null, 'total' => count($challenge->question_ids),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $challenge->attempts()->where('user_id', $user->id)->firstOrFail();
        }
    }
}
