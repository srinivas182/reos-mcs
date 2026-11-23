<?php

declare(strict_types=1);

namespace App\Ask;

use App\Content\Knowledge\Ingestion;
use App\Models\AskMessage;
use App\Models\AskThread;
use App\Models\Course;
use App\Models\InstructorQuestion;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\InstructorAnswered;
use App\Notifications\StudentAsked;
use App\Practice\Flashcards\Flashcards;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Questions for a course's instructors (A10.1b, ADR 0039; screens i09, i10).
 *
 * - **Ask my instructor** from an Ask conversation sends its last question,
 *   with what the course content said, to the instructors of the course
 *   the answer came from (or the course the student studied last).
 * - **Answer:** the student is told and the answer joins their
 *   conversation. **Add to knowledge** files it with the course as a
 *   passage Ask finds for the next student; **Make a flashcard** adds it to
 *   the student's deck.
 *
 * @phpstan-type QuestionRow array{uuid: string, student: string, course: string, question: string, content_answer: string|null, language: string, status: string, answer: string|null, answered_by: string|null, answered_at: string|null, added_to_knowledge: bool, flashcard: bool, asked_at: string|null}
 */
final readonly class InstructorQuestions
{
    public function __construct(private Flashcards $flashcards, private Ingestion $ingestion) {}

    /** @throws ValidationException */
    public function send(User $student, AskThread $thread): InstructorQuestion
    {
        $question = $thread->messages()->where('role', 'question')->latest('id')->first() ?? throw ValidationException::withMessages(['thread' => __('ask.errors.nothingYet')]);
        $answer = $thread->messages()->where('role', 'answer')->where('id', '>', $question->id)->latest('id')->first();
        $lessonId = $answer?->sources[0]['lesson_id'] ?? null;
        $lesson = $lessonId === null ? null : Lesson::query()->find($lessonId);
        $course = ($lesson === null ? null : $lesson->course) ?? $this->lastCourse($student) ?? throw ValidationException::withMessages(['thread' => __('ask.instructor.errors.noCourse')]);
        $instructors = $course->instructors()->get();

        if ($instructors->isEmpty()) {
            throw ValidationException::withMessages(['thread' => __('ask.instructor.errors.noInstructor')]);
        }

        $existing = InstructorQuestion::query()->where('ask_thread_id', $thread->id)->where('question', $question->body)->where('status', 'waiting')->first();
        if ($existing !== null) {
            return $existing;
        }

        $sent = InstructorQuestion::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson?->id, 'ask_thread_id' => $thread->id,
            'question' => $question->body, 'content_answer' => $answer?->body, 'language' => $student->locale->value, 'status' => 'waiting',
        ]);

        foreach ($instructors as $instructor) {
            $instructor->notify(new StudentAsked($sent->uuid, self::firstName($student), $course->title, $sent->question));
        }

        return $sent;
    }

    /**
     * The instructor's queue: waiting first, then answered, for the courses they teach.
     *
     * @return Builder<InstructorQuestion>
     */
    public function for(User $instructor): Builder
    {
        return InstructorQuestion::query()->whereIn('course_id', DB::table('course_instructors')->where('user_id', $instructor->id)->select('course_id'));
    }

    public function canAnswer(User $instructor, InstructorQuestion $question): bool
    {
        return DB::table('course_instructors')->where('user_id', $instructor->id)->where('course_id', $question->course_id)->exists();
    }

    /** @throws ValidationException */
    public function answer(User $instructor, InstructorQuestion $question, string $text, bool $toKnowledge, bool $flashcard): InstructorQuestion
    {
        if ($question->status === 'answered') {
            throw ValidationException::withMessages(['answer' => __('ask.instructor.errors.answered')]);
        }

        $passage = null;

        DB::transaction(function () use ($instructor, $question, $text, $toKnowledge, $flashcard, &$passage): void {
            $question->forceFill(['status' => 'answered', 'answer' => $text, 'answered_by' => $instructor->id, 'answered_at' => CarbonImmutable::now(), 'flashcard' => $flashcard])->save();

            if ($toKnowledge) {
                $passage = $this->toKnowledge($question, $text);
                $question->forceFill(['kb_passage_id' => $passage?->id])->save();
            }

            if ($question->thread !== null) {
                AskMessage::query()->create([
                    'ask_thread_id' => $question->ask_thread_id, 'role' => 'instructor', 'body' => $text, 'tier' => null, 'mode' => null,
                    'sources' => [], 'sponsor' => self::fullName($instructor),
                ]);
                $question->thread->touch();
            }

            if ($flashcard) {
                $this->flashcards->save($question->user, 'ask', $question->question, $text, $question->language);
            }
        });

        if ($passage !== null) {
            // Meaning search too; keyword search works at once.
            rescue(fn () => $this->ingestion->embedMissing($passage->lesson_id), report: false);
        }

        $question->user->notify(new InstructorAnswered($question->thread?->uuid, self::fullName($instructor), $text));

        return $question->refresh();
    }

    /** The answer as a passage of the course, filed with its lesson (or the course's first). */
    private function toKnowledge(InstructorQuestion $question, string $text): ?KbPassage
    {
        $lesson = $question->lesson ?? Lesson::query()->where('course_id', $question->course_id)->orderBy('position')->orderBy('id')->first();

        if ($lesson === null) {
            return null;
        }

        $content = $question->question."\n".$text;

        return KbPassage::query()->create([
            'lesson_id' => $lesson->id, 'course_id' => $question->course_id, 'lesson_media_id' => null, 'states' => $question->course->states,
            'language' => $question->language, 'kind' => KbPassage::INSTRUCTOR, 'heading' => Str::limit($question->question, 197),
            'page' => null, 'seconds' => null, 'position' => 30000, 'content' => $content, 'content_hash' => hash('sha256', $content),
        ]);
    }

    private function lastCourse(User $student): ?Course
    {
        $id = DB::table('course_progress')->where('user_id', $student->id)->orderByDesc('updated_at')->value('course_id');

        return is_int($id) ? Course::query()->find($id) : null;
    }

    /** @return QuestionRow */
    public static function row(InstructorQuestion $q): array
    {
        return [
            'uuid' => $q->uuid, 'student' => self::fullName($q->user), 'course' => $q->course->title, 'question' => $q->question, 'content_answer' => $q->content_answer,
            'language' => $q->language, 'status' => $q->status, 'answer' => $q->answer, 'answered_by' => $q->answerer === null ? null : self::fullName($q->answerer),
            'answered_at' => $q->answered_at?->toIso8601String(), 'added_to_knowledge' => $q->kb_passage_id !== null, 'flashcard' => $q->flashcard, 'asked_at' => $q->created_at?->toIso8601String(),
        ];
    }

    private static function firstName(User $user): string
    {
        return Str::before(trim((string) $user->name), ' ') ?: (string) $user->name;
    }

    private static function fullName(User $user): string
    {
        try {
            return trim((string) $user->name);
        } catch (Throwable) {
            return '';
        }
    }
}
