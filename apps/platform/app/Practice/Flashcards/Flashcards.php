<?php

declare(strict_types=1);

namespace App\Practice\Flashcards;

use App\Models\Flashcard;
use App\Models\LessonProgress;
use App\Models\QuizQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use App\Practice\DiagnosticBank;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Flashcards (candidate screen 31, A9.2d, ADR 0035): each candidate's deck
 * and its FSRS schedule.
 *
 * Cards come from the course's approved flashcards (A7.3) for lessons the
 * candidate has started, from Save as flashcard (a check-in, Ask, or answer
 * review), and from speed-round misses. A session is a two-minute burst of
 * the cards due, then up to 15 new cards a day. The phone keeps the whole
 * deck and reviews offline; it syncs each review with its own id, so it
 * counts once, and the server replays them in order and stays the record.
 *
 * @phpstan-type Card array{id: int, source: string, topic: string|null, front: string, back: string, new: bool, due_at: string|null, reps: int, lapses: int, stability: float|null, difficulty: float|null, last_reviewed_at: string|null}
 * @phpstan-type Deck array{due: int, new_left: int, total: int, cards: list<Card>}
 */
final readonly class Flashcards
{
    public const NEW_PER_DAY = 15;

    /**
     * The whole deck, with what's due and how many new cards are left today.
     *
     * @return Deck
     */
    public function deck(User $user, string $language, ?string $topic = null): array
    {
        $this->syncCourse($user);
        $now = CarbonImmutable::now();
        $cards = Flashcard::query()->where('user_id', $user->id)->when($topic !== null, static fn ($q) => $q->where('topic', $topic))->orderBy('due_at')->orderBy('id')->get();
        $startedToday = Flashcard::query()->where('user_id', $user->id)->whereIn('id', DB::table('flashcard_reviews')->select('flashcard_id')->where('user_id', $user->id)
            ->groupBy('flashcard_id')->havingRaw('min(reviewed_at) >= ?', [StudyPlans::today()->utc()->toDateTimeString()]))->count();

        return [
            'due' => $cards->filter(static fn (Flashcard $c): bool => $c->reps > 0 && $c->due_at !== null && $c->due_at->lessThanOrEqualTo($now))->count(),
            'new_left' => min($cards->where('reps', 0)->count(), max(0, self::NEW_PER_DAY - $startedToday)),
            'total' => $cards->count(),
            'cards' => array_values(array_map(fn (Flashcard $c): array => $this->card($c, $language), $cards->all())),
        ];
    }

    /**
     * Save as flashcard: a check-in, an Ask answer, or a question from answer review.
     *
     * @return Card
     */
    public function save(User $user, string $source, string $front, string $back, string $language, ?string $topic = null, ?int $questionId = null): array
    {
        $question = $questionId === null ? null : QuizQuestion::query()->find($questionId);
        $attributes = ['user_id' => $user->id, 'quiz_question_id' => $question?->id];
        $values = [
            'source' => in_array($source, Flashcard::SOURCES, true) ? $source : 'review',
            'topic' => $question?->topic->value ?? (array_key_exists((string) $topic, DiagnosticBank::WEIGHTS) ? $topic : null),
            'content' => [$language => ['front' => mb_substr(trim($front), 0, 500), 'back' => mb_substr(trim($back), 0, 1000)]],
            'reps' => 0,
            'lapses' => 0,
            'due_at' => now(),
        ];
        $card = $question === null ? Flashcard::query()->create($attributes + $values) : Flashcard::query()->firstOrCreate($attributes, $values);

        return $this->card($card, $language);
    }

    /**
     * Apply reviews, oldest first, each once (by its client id). A review
     * from before the card's last one counts as now.
     *
     * @param  list<array{card_id: int, rating: int, reviewed_at: string, client_id: string}>  $reviews
     * @return array{applied: int, cards: list<Card>}
     */
    public function review(User $user, array $reviews, string $language): array
    {
        usort($reviews, static fn (array $a, array $b): int => strcmp($a['reviewed_at'], $b['reviewed_at']));
        $applied = 0;
        $touched = [];
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($user, $reviews, $now, &$applied, &$touched): void {
            foreach ($reviews as $review) {
                if (DB::table('flashcard_reviews')->where('client_id', $review['client_id'])->exists()) {
                    continue;
                }

                $card = Flashcard::query()->where('user_id', $user->id)->lockForUpdate()->find($review['card_id']);
                if ($card === null) {
                    continue;
                }

                $at = CarbonImmutable::parse($review['reviewed_at'])->min($now);
                $last = $card->last_reviewed_at === null ? null : CarbonImmutable::instance($card->last_reviewed_at);
                $at = $last !== null && $at->lessThan($last) ? $now : $at;
                $next = Fsrs::review(['stability' => $card->stability, 'difficulty' => $card->difficulty, 'reps' => $card->reps, 'lapses' => $card->lapses, 'last_reviewed_at' => $last], $review['rating'], $at);

                $card->forceFill([
                    'stability' => $next['stability'], 'difficulty' => $next['difficulty'], 'reps' => $next['reps'], 'lapses' => $next['lapses'],
                    'last_reviewed_at' => $next['last_reviewed_at'], 'due_at' => $next['due_at'],
                ])->save();
                DB::table('flashcard_reviews')->insert([
                    'flashcard_id' => $card->id, 'user_id' => $user->id, 'client_id' => $review['client_id'], 'rating' => $review['rating'],
                    'reviewed_at' => $at, 'interval_days' => $next['interval_days'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                $applied++;
                $touched[$card->id] = $card;
            }
        });

        return ['applied' => $applied, 'cards' => array_values(array_map(fn (Flashcard $c): array => $this->card($c, $language), $touched))];
    }

    /** Adds the approved flashcards of the lessons the candidate has started. */
    public function syncCourse(User $user): int
    {
        $lessons = LessonProgress::query()->where('user_id', $user->id)->pluck('lesson_id');
        $have = Flashcard::query()->where('user_id', $user->id)->whereNotNull('review_item_id')->pluck('review_item_id')->all();
        $now = now();
        $rows = [];

        foreach (ReviewItem::query()->where('kind', 'flashcard')->where('status', ReviewItem::APPROVED)->whereIn('lesson_id', $lessons)->whereNotIn('id', $have)->get() as $item) {
            $content = [];
            foreach (['en', 'es'] as $l) {
                $side = is_array($item->content[$l] ?? null) ? $item->content[$l] : [];
                if (is_string($side['front'] ?? null) && is_string($side['back'] ?? null)) {
                    $content[$l] = ['front' => $side['front'], 'back' => $side['back']];
                }
            }
            if ($content !== []) {
                $rows[] = ['user_id' => $user->id, 'source' => 'course', 'review_item_id' => $item->id, 'topic' => $item->topic, 'content' => json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'reps' => 0, 'lapses' => 0, 'due_at' => $now, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::table('flashcards')->insert($rows);

        return count($rows);
    }

    /** @return Card */
    private function card(Flashcard $c, string $language): array
    {
        $side = $c->in($language);

        return [
            'id' => $c->id, 'source' => $c->source, 'topic' => $c->topic, 'front' => $side['front'], 'back' => $side['back'], 'new' => $c->reps === 0,
            'due_at' => $c->due_at?->toIso8601String(), 'reps' => $c->reps, 'lapses' => $c->lapses, 'stability' => $c->stability, 'difficulty' => $c->difficulty,
            'last_reviewed_at' => $c->last_reviewed_at?->toIso8601String(),
        ];
    }
}
