<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mentoring;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentoring\MentorSettingsRequest;
use App\Mentoring\MentorDirectory;
use App\Mentoring\MentorProgram;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mentor program on the broker's web (A13.1a, ADR 0053; broker screens
 * 21–23): mentors with focus, load, price and rating; settings; agents
 * with their mentor, and Assign with the best matches. Changes apply at
 * once and are audited.
 */
final class MentorProgramController extends Controller
{
    public function index(#[CurrentUser] User $user, MentorDirectory $directory, MentorProgram $program): Response
    {
        $brokerage = $this->brokerage($user, $directory);

        return Inertia::render('organisation/mentors', ['brokerage' => $brokerage->name, ...$program->program($brokerage)]);
    }

    public function update(MentorSettingsRequest $request, int $mentor, #[CurrentUser] User $user, MentorDirectory $directory, MentorProgram $program): RedirectResponse
    {
        $program->save($this->brokerage($user, $directory), User::query()->findOrFail($mentor), $request->values(), $user);

        return back()->with('success', __('mentoring.program.saved'));
    }

    /** The best mentors for one agent, with reasons (screen 23). */
    public function matches(int $agent, #[CurrentUser] User $user, MentorDirectory $directory, MentorProgram $program): JsonResponse
    {
        return response()->json(['data' => $program->matches($this->brokerage($user, $directory), User::query()->findOrFail($agent))]);
    }

    public function assign(Request $request, #[CurrentUser] User $user, MentorDirectory $directory, MentorProgram $program): RedirectResponse
    {
        $request->validate(['agent' => ['required', 'integer'], 'mentor' => ['required', 'integer']]);
        $program->assign($this->brokerage($user, $directory), User::query()->findOrFail($request->integer('agent')), User::query()->findOrFail($request->integer('mentor')), $user);

        return back()->with('success', __('mentoring.program.assigned'));
    }

    /** Keep or hide a low rating (screen 119's review queue). */
    public function review(Request $request, string $booking, #[CurrentUser] User $user, MentorDirectory $directory, MentorProgram $program): RedirectResponse
    {
        $request->validate(['decision' => ['required', 'in:keep,hide']]);
        $program->review($this->brokerage($user, $directory), $booking, $request->string('decision')->toString() === 'keep', $user);

        return back()->with('success', __('mentoring.program.reviewed'));
    }

    private function brokerage(User $user, MentorDirectory $directory): Organisation
    {
        return $directory->brokerageOf($user, Role::Broker) ?? throw new NotFoundHttpException;
    }
}
