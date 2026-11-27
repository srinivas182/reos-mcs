<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feedback;

use App\Feedback\AgentFeedbacks;
use App\Http\Controllers\Api\Feedback\AgentFeedbackController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\RateAgentRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Feedback from agents on the web (A13.3b; agent screen 125). */
final class AgentFeedbackPageController extends Controller
{
    public function index(#[CurrentUser] User $user, AgentFeedbacks $feedback): Response
    {
        return Inertia::render('feedback/index', AgentFeedbackController::view($user, $feedback));
    }

    public function store(RateAgentRequest $request, #[CurrentUser] User $user, AgentFeedbacks $feedback): RedirectResponse
    {
        $feedback->rate($user, $request->string('deal')->toString(), $request->integer('agent'), $request->scores(), $request->comment());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('feedback.sent')]);

        return back();
    }

    public function respond(Request $request, string $feedback, #[CurrentUser] User $user, AgentFeedbacks $feedbacks): RedirectResponse
    {
        $request->validate(['response' => ['required', 'string', 'min:2', 'max:500']]);
        $feedbacks->respond($user, $feedback, $request->string('response')->toString());

        return back();
    }

    public function report(Request $request, string $feedback, #[CurrentUser] User $user, AgentFeedbacks $feedbacks): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $reason = $request->input('reason');
        $feedbacks->report($user, $feedback, is_string($reason) && trim($reason) !== '' ? $reason : null);

        return back();
    }

    public function visibility(Request $request, #[CurrentUser] User $user, AgentFeedbacks $feedback): RedirectResponse
    {
        $request->validate(['public' => ['required', 'boolean']]);
        $feedback->setPublic($user, $request->boolean('public'));

        return back();
    }
}
