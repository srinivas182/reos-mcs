<?php

declare(strict_types=1);

namespace App\Video;

use App\Content\CourseStatus;
use App\Content\Learning\Learning;
use App\Enums\QuizTopic;
use App\Events\Video\SessionShared;
use App\Messaging\Realtime;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Models\VideoSessionShare;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Quick actions in a session (B6.3b, instructor screen 15): the host opens
 * a lesson for everyone, or sends practice questions they answer in the
 * room. What was shared stays with the session.
 *
 * @phpstan-type ShareData array{id: int, kind: 'lesson'|'questions', at: string, by: string|null, lesson: array{id: int, title: string, course: string}|null, topic: string|null, questions: list<array{id: int, question: string, options: list<string>, correct: int, explanation: string, source: string}>}
 */
final readonly class SessionShares
{
    /** Practice questions sent at a time. */
    public const QUESTIONS = 3;

    public function __construct(private VideoSessions $sessions, private Learning $learning) {}

    /**
     * What the host can share: live lessons in their state, and question topics.
     *
     * @return array{lessons: list<array{id: int, title: string, course: string}>, topics: list<array{topic: string, count: int}>}
     */
    public function options(VideoSession $session, User $host): array
    {
        $this->ensureHost($session, $host);
        $state = $this->learning->state($host);
        $lessons = [];

        $courses = Course::query()->where('status', CourseStatus::Live)->forStates([$state])
            ->with(['lessons' => static fn ($q) => $q->select(['id', 'course_id', 'title', 'position'])])
            ->orderBy('title')->limit(50)->get();

        foreach ($courses as $course) {
            foreach ($course->lessons as $lesson) {
                $lessons[] = ['id' => $lesson->id, 'title' => $lesson->title, 'course' => $course->title];
            }
        }

        $topics = [];

        foreach (QuizTopic::cases() as $topic) {
            $count = $this->questions($state, $topic)->count();

            if ($count > 0) {
                $topics[] = ['topic' => $topic->value, 'count' => $count];
            }
        }

        return ['lessons' => array_slice($lessons, 0, 200), 'topics' => $topics];
    }

    public function shareLesson(VideoSession $session, User $host, int $lessonId): VideoSessionShare
    {
        $this->ensureHost($session, $host);
        $lesson = Lesson::query()->with('course')->find($lessonId);

        if ($lesson === null || $lesson->course->status !== CourseStatus::Live) {
            throw ValidationException::withMessages(['lesson' => __('sessions.share.errors.lesson')]);
        }

        return $this->share($session, $host, VideoSessionShare::LESSON, ['lesson' => $lesson->id, 'title' => $lesson->title, 'course' => $lesson->course->title]);
    }

    public function shareQuestions(VideoSession $session, User $host, QuizTopic $topic): VideoSessionShare
    {
        $this->ensureHost($session, $host);
        $ids = [];

        foreach ($this->questions($this->learning->state($host), $topic)->inRandomOrder()->limit(self::QUESTIONS)->pluck('id') as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }

        if ($ids === []) {
            throw ValidationException::withMessages(['topic' => __('sessions.share.errors.topic')]);
        }

        return $this->share($session, $host, VideoSessionShare::QUESTIONS, ['topic' => $topic->value, 'questions' => $ids]);
    }

    /** @return ShareData */
    public static function present(VideoSessionShare $share, string $locale): array
    {
        $payload = $share->payload;
        $questions = [];

        if ($share->kind === VideoSessionShare::QUESTIONS) {
            $ids = $payload['questions'] ?? [];
            $found = QuizQuestion::query()->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($ids as $id) {
                $question = $found->get($id);

                if ($question === null) {
                    continue;
                }

                $text = $question->content[$locale] ?? $question->content['en'] ?? null;

                if ($text === null) {
                    continue;
                }

                $questions[] = [
                    'id' => $question->id,
                    'question' => $text['question'],
                    'options' => $text['options'],
                    'correct' => $question->correct_option,
                    'explanation' => $text['explanation'],
                    'source' => $question->source,
                ];
            }
        }

        $lesson = $share->kind === VideoSessionShare::LESSON && isset($payload['lesson'])
            ? ['id' => $payload['lesson'], 'title' => $payload['title'] ?? '', 'course' => $payload['course'] ?? '']
            : null;

        return [
            'id' => $share->id,
            'kind' => $share->kind === VideoSessionShare::LESSON ? VideoSessionShare::LESSON : VideoSessionShare::QUESTIONS,
            'at' => $share->created_at->toIso8601String(),
            'by' => $share->user?->name,
            'lesson' => $lesson,
            'topic' => $payload['topic'] ?? null,
            'questions' => $questions,
        ];
    }

    /** @param  array{lesson?: int, title?: string, course?: string, topic?: string, questions?: list<int>}  $payload */
    private function share(VideoSession $session, User $host, string $kind, array $payload): VideoSessionShare
    {
        if ($session->over()) {
            throw ValidationException::withMessages(['session' => __('sessions.errors.over')]);
        }

        $share = $session->shares()->create(['user_id' => $host->id, 'kind' => $kind, 'payload' => $payload]);
        $ids = [];

        foreach ($session->participants()->pluck('user_id') as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }

        Realtime::send(new SessionShared($session, $ids));

        return $share;
    }

    /** @return Builder<QuizQuestion> */
    private function questions(string $state, QuizTopic $topic): Builder
    {
        return QuizQuestion::query()->where('topic', $topic)->where(fn ($q) => $q->whereNull('state_code')->orWhere('state_code', $state));
    }

    private function ensureHost(VideoSession $session, User $user): void
    {
        if ($this->sessions->ensureParticipant($session, $user)->role !== VideoSessionParticipant::HOST) {
            throw ValidationException::withMessages(['session' => __('sessions.errors.hostOnly')]);
        }
    }
}
