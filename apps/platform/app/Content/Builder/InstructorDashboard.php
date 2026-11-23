<?php

declare(strict_types=1);

namespace App\Content\Builder;

use App\Authorization\Role;
use App\Enums\QuizTopic;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Organisation;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\VideoSession;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The instructor's dashboard (web screen 42, A7.2): lessons to review,
 * sessions this week, the practice pass rate of the school's candidates,
 * today's list, and the most-missed topics (to draft a lesson from, A7.3).
 * An instructor sees only their own courses.
 */
final readonly class InstructorDashboard
{
    /** Practice score that counts as a pass (the TREC exam's passing score is 70%, 22 TAC §535.65(h)). */
    public const PASS_PERCENT = 70;

    public function __construct(private Memberships $memberships) {}

    /**
     * @return array{courses: int, reviews: int, drafts: int, sessions_week: int, pass_rate: int|null, rating: float|null, today: list<array{id: string, title: string, starts_at: string}>, missed: list<array{topic: string, missed: int, answered: int}>}
     */
    public function for(User $instructor, Organisation $school, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $courseIds = Course::query()->where('organisation_id', $school->id)
            ->whereHas('instructors', static fn ($q) => $q->where('users.id', $instructor->id))->pluck('id');
        $lessons = Lesson::query()->whereIn('course_id', $courseIds);

        $sessions = VideoSession::query()->where('host_id', $instructor->id)->where('status', '!=', VideoSession::CANCELLED)
            ->whereBetween('starts_at', [$now->startOfDay(), $now->addDays(7)])->orderBy('starts_at')->get();

        $candidates = $this->memberships->holdersIn(Role::Candidate, $school)->pluck('id');
        $attempts = QuizAttempt::query()->whereIn('user_id', $candidates)->where('completed_at', '>=', $now->subDays(30))->get();

        return [
            'courses' => $courseIds->count(),
            'reviews' => (clone $lessons)->where('review_status', LessonReview::InReview->value)->count(),
            'drafts' => (clone $lessons)->where('review_status', LessonReview::Draft->value)->count(),
            'sessions_week' => $sessions->count(),
            'pass_rate' => $attempts->isEmpty() ? null : (int) round(100 * $attempts->filter(static fn (QuizAttempt $a): bool => $a->total > 0 && 100 * $a->correct / $a->total >= self::PASS_PERCENT)->count() / $attempts->count()),
            // Ratings arrive with reviews.
            'rating' => null,
            'today' => array_values($sessions->filter(static fn (VideoSession $s): bool => $s->starts_at->isSameDay($now))
                ->map(static fn (VideoSession $s): array => ['id' => $s->uuid, 'title' => $s->title, 'starts_at' => $s->starts_at->toIso8601String()])->all()),
            'missed' => $this->missed($attempts),
        ];
    }

    /**
     * The topics the school's candidates missed most in the last 30 days.
     *
     * @param  Collection<int, QuizAttempt>  $attempts
     * @return list<array{topic: string, missed: int, answered: int}>
     */
    private function missed(Collection $attempts): array
    {
        $answers = $attempts->flatMap(static fn (QuizAttempt $a): array => $a->answers);
        $topics = QuizQuestion::query()->whereIn('id', $answers->pluck('question_id')->unique()->values())->pluck('topic', 'id');
        $tally = [];

        foreach ($answers as $answer) {
            $topic = $topics->get($answer['question_id']);

            if (! $topic instanceof QuizTopic) {
                continue;
            }

            $key = $topic->value;
            $tally[$key] ??= ['topic' => $key, 'missed' => 0, 'answered' => 0];
            $tally[$key]['answered']++;
            $tally[$key]['missed'] += $answer['correct'] ? 0 : 1;
        }

        usort($tally, static fn (array $a, array $b): int => [$b['missed'], $a['topic']] <=> [$a['missed'], $b['topic']]);

        return array_slice(array_values(array_filter($tally, static fn (array $t): bool => $t['missed'] > 0)), 0, 3);
    }
}
