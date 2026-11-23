<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Ask\InstructorQuestions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\AnswerQuestionRequest;
use App\Models\AskMessage;
use App\Models\InstructorQuestion;
use App\Models\Lesson;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Student questions (A10.1b, instructor screens i09 and i10; ADR 0039):
 * what the course content couldn't answer, for the instructors of the
 * course. Answer once, and help every future student.
 */
final class QuestionController extends Controller
{
    /**
     * Student questions.
     *
     * Waiting or answered, for the courses the instructor teaches, newest first; and how many questions Ask answered from their courses' content in the last 30 days.
     *
     * @response array{data: list<array{uuid: string, student: string, course: string, question: string, content_answer: string|null, language: string, status: 'waiting'|'answered', answer: string|null, answered_by: string|null, answered_at: string|null, added_to_knowledge: bool, flashcard: bool, asked_at: string|null}>, counts: array{waiting: int, answered: int, from_content: int}}
     */
    public function index(Request $request, #[CurrentUser] User $user, InstructorQuestions $questions): JsonResponse
    {
        // `waiting` (the default) or `answered`.
        $request->validate([
            'status' => ['sometimes', Rule::in(['waiting', 'answered'])],
        ]);

        return response()->json(self::listing($request, $user, $questions));
    }

    /**
     * One student question.
     *
     * @response array{data: array{uuid: string, student: string, course: string, question: string, content_answer: string|null, language: string, status: 'waiting'|'answered', answer: string|null, answered_by: string|null, answered_at: string|null, added_to_knowledge: bool, flashcard: bool, asked_at: string|null}}
     */
    public function show(InstructorQuestion $question, #[CurrentUser] User $user, InstructorQuestions $questions): JsonResponse
    {
        abort_unless($questions->canAnswer($user, $question), 404);

        return response()->json(['data' => InstructorQuestions::row($question)]);
    }

    /**
     * Answer a student.
     *
     * The student is notified and the answer joins their Ask conversation. With `add_to_knowledge`, Ask uses it for the next student who asks.
     *
     * @response array{data: array{uuid: string, student: string, course: string, question: string, content_answer: string|null, language: string, status: 'waiting'|'answered', answer: string|null, answered_by: string|null, answered_at: string|null, added_to_knowledge: bool, flashcard: bool, asked_at: string|null}}
     */
    public function answer(InstructorQuestion $question, AnswerQuestionRequest $request, #[CurrentUser] User $user, InstructorQuestions $questions): JsonResponse
    {
        abort_unless($questions->canAnswer($user, $question), 404);
        $answered = $questions->answer($user, $question, trim($request->string('answer')->toString()), $request->boolean('add_to_knowledge'), $request->boolean('flashcard'));

        return response()->json(['data' => InstructorQuestions::row($answered)]);
    }

    /** @return array{data: list<array<string, mixed>>, counts: array{waiting: int, answered: int, from_content: int}} */
    public static function listing(Request $request, User $user, InstructorQuestions $questions): array
    {
        $status = $request->query('status') === 'answered' ? 'answered' : 'waiting';
        $rows = $questions->for($user)->where('status', $status)->with(['user', 'course', 'answerer'])->latest('id')->limit(100)->get();

        return [
            'data' => array_values($rows->map(InstructorQuestions::row(...))->all()),
            'counts' => [
                'waiting' => $questions->for($user)->where('status', 'waiting')->count(),
                'answered' => $questions->for($user)->where('status', 'answered')->count(),
                'from_content' => self::fromContent($user),
            ],
        ];
    }

    /** Questions Ask answered from the instructor's courses in the last 30 days. */
    private static function fromContent(User $user): int
    {
        $courses = DB::table('course_instructors')->where('user_id', $user->id)->pluck('course_id')->all();

        if ($courses === []) {
            return 0;
        }

        $lessons = [];
        foreach (Lesson::query()->whereIn('course_id', $courses)->get(['id']) as $lesson) {
            $lessons[$lesson->id] = true;
        }
        $count = 0;

        foreach (AskMessage::query()->where('tier', 'content')->where('created_at', '>=', CarbonImmutable::now()->subDays(30))->latest('id')->limit(5000)->get(['id', 'sources']) as $message) {
            $first = $message->sources[0]['lesson_id'] ?? null;
            if (is_int($first) && isset($lessons[$first])) {
                $count++;
            }
        }

        return $count;
    }
}
