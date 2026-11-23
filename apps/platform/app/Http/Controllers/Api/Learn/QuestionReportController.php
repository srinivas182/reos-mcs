<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Learn;

use App\Content\Review\QuestionBank;
use App\Http\Controllers\Controller;
use App\Models\QuestionDispute;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Learners report a question they think is wrong (A8.1): its owner keeps or corrects it, and they're told. */
final class QuestionReportController extends Controller
{
    /**
     * Report a question.
     *
     * One open report per person and question; sending again returns the same report.
     *
     * @response 201 array{id: int, status: string}
     */
    public function store(Request $request, QuizQuestion $question, #[CurrentUser] User $user, QuestionBank $bank): JsonResponse
    {
        abort_if($question->retired_at !== null, 404);
        $data = $request->validate([
            'reason' => ['required', Rule::in(QuestionDispute::REASONS)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        $dispute = $bank->dispute($question, $user, (string) $data['reason'], is_string($data['details'] ?? null) ? $data['details'] : null);

        return response()->json(['id' => $dispute->id, 'status' => $dispute->status], $dispute->wasRecentlyCreated ? 201 : 200);
    }
}
