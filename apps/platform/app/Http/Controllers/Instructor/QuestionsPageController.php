<?php

declare(strict_types=1);

namespace App\Http\Controllers\Instructor;

use App\Ask\InstructorQuestions;
use App\Http\Controllers\Api\Instructor\QuestionController;
use App\Http\Controllers\Controller;
use App\Models\InstructorQuestion;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Student questions on the web (A10.1b, instructor screens i09 and i10). */
final class QuestionsPageController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, InstructorQuestions $questions): Response
    {
        $open = $request->query('question');
        $selected = is_string($open) ? InstructorQuestion::query()->where('uuid', $open)->first() : null;

        return Inertia::render('instructor/questions', [
            ...QuestionController::listing($request, $user, $questions),
            'status' => $request->query('status') === 'answered' ? 'answered' : 'waiting',
            'selected' => $selected !== null && $questions->canAnswer($user, $selected) ? InstructorQuestions::row($selected) : null,
        ]);
    }
}
