<?php

declare(strict_types=1);

namespace App\Content\Player;

use App\Content\Approvals\CourseApprovals;
use App\Content\CourseType;
use App\Models\CeCompletion;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\CourseProgress;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonVersion;
use App\Models\OfflineGrant;
use App\Models\QuizQuestion;
use App\Models\ReviewItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seat time (A8.2, ADR 0030). The player sends a heartbeat every 15 seconds;
 * the server decides what counts, so nothing the browser or phone says is
 * trusted on its own:
 *
 * - only real time between two heartbeats of the same viewing (at most 20
 *   seconds each, so speeding up the video never adds time);
 * - only while the video plays and the page or app is in front;
 * - never while a check-in is waiting to be answered;
 * - never beyond the approval's maximum hours a day (the learner's own day).
 *
 * A lesson completes when its required time (minutes × the approval's
 * presence share) has counted and every check-in is answered; a course when
 * every lesson is complete, and a CE or qualifying course then records the
 * completion. Every stretch, counted or not, is logged with why.
 *
 * @phpstan-type Checkpoint array{key: string, at: int, kind: 'question'|'presence', question: array{id: int, prompt: string, options: list<string>}|null}
 * @phpstan-type State array{counted: int, required: int, percent: int, reason: string|null, checkin: Checkpoint|null, completed: bool, today_left: int|null}
 */
final readonly class SeatTime
{
    /** Heartbeats come every 15 seconds; a stretch counts at most this much. */
    public const MAX_STRETCH = 20;

    /** An offline stretch counts at most this much; the phone splits longer ones. */
    public const MAX_OFFLINE_STRETCH = 900;

    /** A longer silence starts a new viewing: the gap doesn't count. */
    public const GAP = 45;

    /** Logged stretches with the same outcome within this many seconds are one row. */
    private const MERGE = 30;

    /**
     * The seat-time rules in force for a course (not named `rules()`: Scramble
     * would read it as validation rules): the approval's (A7.1), else REOS's defaults.
     *
     * @return array{checkin_minutes: int, presence_percent: int, max_seconds_per_day: int|null}
     */
    public function rulesFor(Course $course): array
    {
        $approval = $course->approvals()->get()->first(static fn (CourseApproval $a): bool => $a->valid());
        $max = $approval?->max_hours_per_day;

        return [
            'checkin_minutes' => max(1, $approval->checkin_minutes ?? CourseApprovals::CHECKIN_MINUTES),
            'presence_percent' => min(100, max(1, $approval->presence_percent ?? 100)),
            'max_seconds_per_day' => is_numeric($max) ? (int) round((float) $max * 3600) : null,
        ];
    }

    /**
     * One heartbeat from the player.
     *
     * @param  array{position: int, playing: bool, visible: bool, session: string, timezone: string, source: string}  $beat
     * @return State
     */
    public function beat(User $user, Lesson $lesson, array $beat, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($user, $lesson, $beat, $now): array {
            $progress = $this->progress($user, $lesson);
            $progress = LessonProgress::query()->whereKey($progress->id)->lockForUpdate()->firstOrFail();
            $course = $lesson->course;
            $rules = $this->rulesFor($course);
            $last = $progress->last_beat_at === null ? null : CarbonImmutable::parse($progress->last_beat_at);
            $sameViewing = $last !== null && $progress->session === $beat['session'] && $now->greaterThanOrEqualTo($last) && $last->diffInSeconds($now) <= self::GAP;
            $elapsed = $sameViewing ? (int) min(self::MAX_STRETCH, floor($last->diffInSeconds($now))) : 0;
            $checkin = $this->due($lesson, $progress);
            $day = $now->setTimezone(self::zone($beat['timezone']))->toDateString();
            $used = DB::table('seat_time_days')->where('user_id', $user->id)->where('course_id', $course->id)->where('day', $day)->value('seconds');
            $usedToday = is_numeric($used) ? (int) $used : 0;
            $left = $rules['max_seconds_per_day'] === null ? null : max(0, $rules['max_seconds_per_day'] - $usedToday);

            $reason = match (true) {
                ! $sameViewing && $last !== null => 'gap',
                ! $beat['playing'] => 'paused',
                ! $beat['visible'] => 'hidden',
                $checkin !== null => 'checkin_due',
                $left === 0 => 'daily_max',
                default => null,
            };
            $counted = $reason === null ? ($left === null ? $elapsed : min($elapsed, $left)) : 0;

            $progress->forceFill([
                'counted_seconds' => $progress->counted_seconds + $counted,
                'position' => max(0, $beat['position']),
                'session' => $beat['session'],
                'last_beat_at' => $now,
            ])->save();

            if ($counted > 0) {
                $row = ['user_id' => $user->id, 'course_id' => $course->id, 'day' => $day];
                DB::table('seat_time_days')->insertOrIgnore([...$row, 'seconds' => 0, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('seat_time_days')->where($row)->increment('seconds', $counted, ['updated_at' => $now]);
            }

            $this->log($user, $lesson, $counted, $reason === null ? 0 : $elapsed, $reason, $beat['source'], null, $now);
            $this->settle($user, $lesson, $progress);

            return $this->state($lesson, $progress->refresh(), $reason, $left === null ? null : $left - $counted);
        });
    }

    /**
     * The learner answers a check-in. A wrong answer still counts as present;
     * it shows why, with the question's source.
     *
     * @return array{correct: bool|null, explanation: string|null, source: string|null, state: State}
     */
    public function answer(User $user, Lesson $lesson, string $key, ?int $choice, string $language = 'en', ?CarbonImmutable $at = null): array
    {
        $progress = $this->progress($user, $lesson);
        $checkpoint = collect($this->checkpoints($lesson, $progress->required_seconds))->firstWhere('key', $key);

        if ($checkpoint === null) {
            throw ValidationException::withMessages(['key' => __('player.errors.checkin')]);
        }

        $correct = null;
        $explanation = null;
        $source = null;

        if ($checkpoint['kind'] === 'question' && $checkpoint['question'] !== null) {
            $question = QuizQuestion::query()->findOrFail($checkpoint['question']['id']);
            $correct = $choice === $question->correct_option;
            $explanation = $question->in($language)['explanation'];
            $source = $question->source;
        }

        $checkins = $progress->checkins ?? [];
        $checkins[$key] ??= ['correct' => $correct, 'at' => ($at ?? CarbonImmutable::now())->toIso8601String()];
        $progress->forceFill(['checkins' => $checkins])->save();
        $this->settle($user, $lesson, $progress);

        return ['correct' => $correct, 'explanation' => $explanation, 'source' => $source, 'state' => $this->state($lesson, $progress->refresh(), null, null)];
    }

    /**
     * Seat time recorded on the phone while offline (A8.2b), replayed when it
     * is back online. The phone signed it with the grant's key, but nothing
     * it says is trusted on its own:
     *
     * - events are applied in time order, whatever order they arrive in, and
     *   each only once (by its id);
     * - a stretch must lie between the grant and now, lasts at most 15
     *   minutes, and the part that overlaps any other offline stretch of the
     *   learner (on any lesson) never counts;
     * - it stops at the next check-in until that is answered, and at the
     *   approval's daily maximum on the learner's own day.
     *
     * @param  list<array{id: string, type: 'watch'|'checkin', at: string, seconds?: int, key?: string, choice?: int|null}>  $events
     * @return array{accepted: int, duplicates: int, rejected: int, state: State}
     */
    public function replay(User $user, OfflineGrant $grant, array $events, string $timezone, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $lesson = $grant->lesson;
        $earliest = CarbonImmutable::parse($grant->created_at ?? $now)->subMinutes(5);
        $latest = $now->addMinutes(2);
        usort($events, static fn (array $a, array $b): int => [$a['at'], $a['id']] <=> [$b['at'], $b['id']]);

        return DB::transaction(function () use ($user, $lesson, $grant, $events, $timezone, $now, $earliest, $latest): array {
            $progress = $this->progress($user, $lesson);
            $progress = LessonProgress::query()->whereKey($progress->id)->lockForUpdate()->firstOrFail();
            $rules = $this->rulesFor($lesson->course);
            $tally = ['accepted' => 0, 'duplicates' => 0, 'rejected' => 0];

            foreach ($events as $event) {
                $segment = 'offline:'.$grant->id.':'.mb_substr($event['id'], 0, 40);

                if (DB::table('seat_time_events')->where('user_id', $user->id)->where('segment', $segment)->exists()) {
                    $tally['duplicates']++;

                    continue;
                }

                $at = rescue(static fn (): CarbonImmutable => CarbonImmutable::parse($event['at']), null, false);
                $seconds = max(0, min(self::MAX_OFFLINE_STRETCH, (int) ($event['seconds'] ?? 0)));
                $end = $at?->addSeconds($event['type'] === 'watch' ? $seconds : 0);

                if ($at === null || $end === null || $at->lessThan($earliest) || $end->greaterThan($latest)) {
                    $this->logOffline($user, $lesson, 0, $seconds, 'invalid', $segment, $at ?? $now, $end ?? $now, $now);
                    $tally['rejected']++;

                    continue;
                }

                if ($event['type'] === 'checkin') {
                    try {
                        $this->answer($user, $lesson, (string) ($event['key'] ?? ''), $event['choice'] ?? null, $user->locale->value, $at);
                    } catch (ValidationException) {
                        $this->logOffline($user, $lesson, 0, 0, 'invalid', $segment, $at, $at, $now);
                        $tally['rejected']++;

                        continue;
                    }
                    $progress->refresh();
                    $this->logOffline($user, $lesson, 0, 0, 'checkin', $segment, $at, $at, $now);
                    $tally['accepted']++;

                    continue;
                }

                // Never the same moment twice: drop what overlaps other offline stretches.
                $overlap = 0;
                foreach (DB::table('seat_time_events')->where('user_id', $user->id)->whereNotNull('started_at')->where('counted', '>', 0)
                    ->where('started_at', '<', $end)->where('ended_at', '>', $at)->get(['started_at', 'ended_at']) as $other) {
                    $from = max($at->getTimestamp(), CarbonImmutable::parse((string) $other->started_at)->getTimestamp());
                    $to = min($end->getTimestamp(), CarbonImmutable::parse((string) $other->ended_at)->getTimestamp());
                    $overlap += max(0, $to - $from);
                }
                $real = max(0, $seconds - $overlap);

                $day = $at->setTimezone(self::zone($timezone))->toDateString();
                $used = DB::table('seat_time_days')->where('user_id', $user->id)->where('course_id', $lesson->course_id)->where('day', $day)->value('seconds');
                $left = $rules['max_seconds_per_day'] === null ? null : max(0, $rules['max_seconds_per_day'] - (is_numeric($used) ? (int) $used : 0));
                $next = $this->nextCheckpoint($lesson, $progress);

                $reason = match (true) {
                    $real === 0 => 'overlap',
                    $next !== null && $next['at'] <= $progress->counted_seconds => 'checkin_due',
                    $left === 0 => 'daily_max',
                    default => null,
                };
                $counted = $reason !== null ? 0 : min($real, $left ?? $real, $next === null ? $real : $next['at'] - $progress->counted_seconds);

                if ($counted > 0) {
                    $progress->forceFill(['counted_seconds' => $progress->counted_seconds + $counted])->save();
                    $row = ['user_id' => $user->id, 'course_id' => $lesson->course_id, 'day' => $day];
                    DB::table('seat_time_days')->insertOrIgnore([...$row, 'seconds' => 0, 'created_at' => $now, 'updated_at' => $now]);
                    DB::table('seat_time_days')->where($row)->increment('seconds', $counted, ['updated_at' => $now]);
                }

                // A stretch that ran into a check-in or the daily maximum says so.
                $reason ??= $counted < $real ? ($left !== null && $counted === $left ? 'daily_max' : 'checkin_due') : null;
                $this->logOffline($user, $lesson, $counted, $seconds - $counted, $reason, $segment, $at, $at->addSeconds($counted), $now);
                $tally['accepted']++;
            }

            $this->settle($user, $lesson, $progress->refresh());

            return [...$tally, 'state' => $this->state($lesson, $progress->refresh(), null, null)];
        });
    }

    /** @return list<string> the check-ins already answered, for the phone offline */
    public function answered(User $user, Lesson $lesson): array
    {
        return array_map('strval', array_keys($this->progress($user, $lesson)->checkins ?? []));
    }

    /** @return State */
    public function current(User $user, Lesson $lesson): array
    {
        return $this->state($lesson, $this->progress($user, $lesson), null, null);
    }

    /**
     * The lesson's check-in points, at so many counted seconds: an approved AI
     * lesson's check-ins (A8.1) where they fall in the script, otherwise one
     * every so many minutes from the approval's rule. Each uses one of the
     * lesson's approved questions when it has them, else a presence check.
     *
     * @return list<Checkpoint>
     */
    public function checkpoints(Lesson $lesson, int $required, string $language = 'en'): array
    {
        $version = LessonVersion::query()->where('lesson_id', $lesson->id)->where('review_state', 'approved')->orderByDesc('version')->first();
        $points = [];

        if ($version !== null) {
            $lines = max(1, count($version->lines ?? []));
            $items = ReviewItem::query()->where('lesson_id', $lesson->id)->where('kind', 'checkin')->where('status', ReviewItem::APPROVED)->orderBy('after_line')->get();

            foreach ($items as $item) {
                $points[] = ['key' => 'checkin:'.$item->id, 'at' => (int) floor($required * min(1, (($item->after_line ?? 0) + 1) / $lines))];
            }
        } else {
            $every = $this->rulesFor($lesson->course)['checkin_minutes'] * 60;
            for ($at = $every; $at < $required; $at += $every) {
                $points[] = ['key' => 'presence:'.intdiv($at, $every), 'at' => $at];
            }
        }

        $questions = QuizQuestion::query()->whereNull('retired_at')
            ->whereIn('review_item_id', ReviewItem::query()->where('lesson_id', $lesson->id)->where('kind', 'question')->where('status', ReviewItem::APPROVED)->select('id'))
            ->orderBy('id')->get()->values();

        return array_map(static function (array $p, int $i) use ($questions, $language): array {
            $question = $questions->get($i);

            return $question instanceof QuizQuestion
                ? [...$p, 'kind' => 'question', 'question' => ['id' => $question->id, 'prompt' => $question->in($language)['question'], 'options' => $question->in($language)['options']]]
                : [...$p, 'kind' => 'presence', 'question' => null];
        }, $points, array_keys($points));
    }

    /** The share of a course done (0–100), for Learn and the refund policy. */
    public function coursePercent(User $user, Course $course): int
    {
        $percent = CourseProgress::query()->where('user_id', $user->id)->where('course_id', $course->id)->value('percent');

        return is_numeric($percent) ? (int) $percent : 0;
    }

    private function progress(User $user, Lesson $lesson): LessonProgress
    {
        $required = max(60, (int) round(($lesson->minutes ?? 1) * 60 * $this->rulesFor($lesson->course)['presence_percent'] / 100));

        $progress = LessonProgress::query()->firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id], ['required_seconds' => $required]);

        if ($progress->wasRecentlyCreated) {
            $progress->refresh(); // the table's defaults (nothing counted yet)
        }

        if ($progress->completed_at === null && $progress->required_seconds !== $required) {
            $progress->forceFill(['required_seconds' => $required])->save();
        }

        return $progress;
    }

    /** @return Checkpoint|null the check-in waiting to be answered */
    private function due(Lesson $lesson, LessonProgress $progress): ?array
    {
        foreach ($this->checkpoints($lesson, $progress->required_seconds) as $point) {
            if ($progress->counted_seconds >= $point['at'] && ! isset(($progress->checkins ?? [])[$point['key']])) {
                return $point;
            }
        }

        return null;
    }

    /** @return Checkpoint|null the first check-in not yet answered, due or not */
    private function nextCheckpoint(Lesson $lesson, LessonProgress $progress): ?array
    {
        foreach ($this->checkpoints($lesson, $progress->required_seconds) as $point) {
            if (! isset(($progress->checkins ?? [])[$point['key']])) {
                return $point;
            }
        }

        return null;
    }

    /** Logs one offline event under its own id (never merged), with its real start and end. */
    private function logOffline(User $user, Lesson $lesson, int $counted, int $uncounted, ?string $reason, string $segment, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $now): void
    {
        DB::table('seat_time_events')->insert([
            'user_id' => $user->id, 'lesson_id' => $lesson->id, 'counted' => $counted, 'uncounted' => max(0, $uncounted), 'reason' => $reason, 'source' => 'offline',
            'segment' => $segment, 'occurred_at' => $end, 'started_at' => $start, 'ended_at' => $end, 'created_at' => $now,
        ]);
    }

    /** Completes the lesson and, with it, perhaps the course. */
    private function settle(User $user, Lesson $lesson, LessonProgress $progress): void
    {
        if ($progress->completed_at === null && $progress->counted_seconds >= $progress->required_seconds) {
            $answered = $progress->checkins ?? [];
            $all = array_column($this->checkpoints($lesson, $progress->required_seconds), 'key');

            if (array_diff($all, array_keys($answered)) === []) {
                $progress->forceFill(['completed_at' => now()])->save();
            }
        }

        $course = $lesson->course;
        $lessons = Lesson::query()->learnable()->where('course_id', $course->id)->pluck('id');
        $rows = LessonProgress::query()->where('user_id', $user->id)->whereIn('lesson_id', $lessons)->get();
        $done = $rows->whereNotNull('completed_at')->count();
        $required = max(1, (int) $rows->sum(static fn (LessonProgress $p): int => $p->required_seconds) + max(0, $lessons->count() - $rows->count()) * 60);
        $counted = (int) $rows->sum(static fn (LessonProgress $p): int => min($p->counted_seconds, $p->required_seconds));
        $percent = (int) min(100, floor(100 * $counted / $required));
        $complete = $lessons->isNotEmpty() && $done === $lessons->count();

        $courseProgress = CourseProgress::query()->firstOrNew(['user_id' => $user->id, 'course_id' => $course->id]);
        $newlyComplete = $complete && $courseProgress->completed_at === null;
        $courseProgress->forceFill([
            'counted_seconds' => (int) $rows->sum(static fn (LessonProgress $p): int => $p->counted_seconds), 'lessons_done' => $done, 'lessons_total' => $lessons->count(),
            'percent' => $complete ? 100 : min(99, $percent), 'completed_at' => $complete ? ($courseProgress->completed_at ?? now()) : null,
        ])->save();

        if ($newlyComplete && in_array($course->type, [CourseType::Ce, CourseType::Qualifying], true)) {
            CeCompletion::query()->firstOrCreate(
                ['user_id' => $user->id, 'title' => $course->title, 'source' => 'reos', 'completed_on' => now()->toDateString()],
                ['state_code' => 'TX', 'hours' => number_format((float) ($course->hours ?? 0), 1, '.', ''), 'provider' => $course->owner->name ?? 'REOS', 'course_code' => null, 'course_id' => $course->id],
            );
        }
    }

    /** @return State */
    private function state(Lesson $lesson, LessonProgress $progress, ?string $reason, ?int $todayLeft): array
    {
        $required = max(1, $progress->required_seconds);

        return [
            'counted' => $progress->counted_seconds,
            'required' => $progress->required_seconds,
            'percent' => (int) min(100, floor(100 * $progress->counted_seconds / $required)),
            'reason' => $reason,
            'checkin' => $this->due($lesson, $progress),
            'completed' => $progress->completed_at !== null,
            'today_left' => $todayLeft,
        ];
    }

    /** Logs a stretch, merging it into the last row when the outcome is the same. */
    private function log(User $user, Lesson $lesson, int $counted, int $uncounted, ?string $reason, string $source, ?string $segment, CarbonImmutable $now): void
    {
        if ($counted === 0 && $uncounted === 0 && $reason === null) {
            return;
        }

        $last = $segment !== null ? null : DB::table('seat_time_events')->where('user_id', $user->id)->where('lesson_id', $lesson->id)->whereNull('segment')
            ->orderByDesc('id')->first();

        if ($last !== null && $last->reason === $reason && $last->source === $source && is_string($last->occurred_at) && CarbonImmutable::parse($last->occurred_at)->diffInSeconds($now) <= self::MERGE) {
            DB::table('seat_time_events')->where('id', $last->id)->incrementEach(['counted' => $counted, 'uncounted' => $uncounted], ['occurred_at' => $now]);

            return;
        }

        DB::table('seat_time_events')->insert(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'counted' => $counted, 'uncounted' => $uncounted, 'reason' => $reason, 'source' => $source, 'segment' => $segment, 'occurred_at' => $now, 'created_at' => $now]);
    }

    private static function zone(string $timezone): string
    {
        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'America/Chicago';
    }
}
