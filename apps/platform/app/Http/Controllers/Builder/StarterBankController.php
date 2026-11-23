<?php

declare(strict_types=1);

namespace App\Http\Controllers\Builder;

use App\Authorization\Role;
use App\Content\Builder\CourseBuilder;
use App\Content\Review\StarterReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\QuestionEditRequest;
use App\Http\Requests\Builder\StarterDecisionRequest;
use App\Models\Organisation;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Practice\StarterBank;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * The question bank's Starter bank tab (screen 45, A9.1, decision 2): a
 * school admin approves, retires, restores (undo) or edits REOS's Texas
 * starter questions. Listing is on QuestionBankController::index.
 */
final class StarterBankController extends Controller
{
    public function decide(StarterDecisionRequest $request, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, StarterReview $review): RedirectResponse
    {
        $school = self::admin($user, $roles, $builder);
        $review->decide($request->ids(), $request->decision(), $user, $school->id);

        return back();
    }

    public function update(QuestionEditRequest $request, QuizQuestion $question, #[CurrentUser] User $user, ActiveRole $roles, CourseBuilder $builder, StarterReview $review): RedirectResponse
    {
        $school = self::admin($user, $roles, $builder);
        abort_unless(is_string($question->key) && str_starts_with($question->key, StarterBank::PREFIX), 404);
        $review->edit($question, $request->edit(), $user, $school->id);

        return back()->with('success', __('review.questionSaved'));
    }

    /** The school, for its admins only: the starter bank is reviewed once per deployment, not per instructor. */
    public static function admin(User $user, ActiveRole $roles, CourseBuilder $builder): Organisation
    {
        $membership = $roles->for($user);
        $school = $builder->school($membership);
        abort_unless($school !== null && $membership?->role === Role::SchoolAdmin, 403);

        return $school;
    }
}
