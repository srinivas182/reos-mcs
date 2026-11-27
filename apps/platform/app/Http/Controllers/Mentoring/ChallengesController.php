<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mentoring;

use App\Agents\Activity;
use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Mentoring\MentorDirectory;
use App\Models\User;
use App\Pods\BrokerageChallenges;
use App\Pods\Pods;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pods and challenges on the web (A13.2, ADR 0054): the broker sets
 * challenges at /organisation/challenges; an agent sees their pod and the
 * brokerage's challenges at /pod.
 */
final class ChallengesController extends Controller
{
    public function broker(#[CurrentUser] User $user, MentorDirectory $directory, BrokerageChallenges $challenges): Response
    {
        $brokerage = $directory->brokerageOf($user, Role::Broker) ?? throw new NotFoundHttpException;

        return Inertia::render('organisation/challenges', ['brokerage' => $brokerage->name, 'metrics' => Activity::METRICS, 'challenges' => $challenges->list($user, $brokerage)]);
    }

    public function store(Request $request, #[CurrentUser] User $user, BrokerageChallenges $challenges): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'metric' => ['required', 'in:'.implode(',', Activity::METRICS)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $challenges->create($user, $request->string('title')->toString(), $request->string('metric')->toString(), $request->string('starts_on')->toString(), $request->string('ends_on')->toString());

        return back()->with('success', __('pods.challenges.created'));
    }

    public function agent(#[CurrentUser] User $user, Pods $pods, BrokerageChallenges $challenges): Response
    {
        return Inertia::render('pod/index', ['pod' => $pods->mine($user), 'challenges' => $challenges->list($user)]);
    }

    public function join(#[CurrentUser] User $user, Pods $pods): RedirectResponse
    {
        $pods->join($user);

        return back();
    }

    /** Leaves the pod (the page offers Undo with the pod it showed). */
    public function leave(#[CurrentUser] User $user, Pods $pods): RedirectResponse
    {
        $pods->leave($user);

        return back();
    }

    public function rejoin(Request $request, #[CurrentUser] User $user, Pods $pods): RedirectResponse
    {
        $request->validate(['pod' => ['required', 'uuid']]);
        $pods->rejoin($user, $request->string('pod')->toString());

        return back();
    }

    public function goal(Request $request, #[CurrentUser] User $user, Pods $pods): RedirectResponse
    {
        $request->validate(['text' => ['required', 'string', 'min:2', 'max:200']]);
        $pods->setGoal($user, $request->string('text')->toString());

        return back()->with('success', __('pods.saved'));
    }

    public function joinChallenge(string $challenge, #[CurrentUser] User $user, BrokerageChallenges $challenges): RedirectResponse
    {
        $challenges->join($user, $challenge);

        return back()->with('success', __('pods.challenges.joined'));
    }

    public function leaveChallenge(string $challenge, #[CurrentUser] User $user, BrokerageChallenges $challenges): RedirectResponse
    {
        $challenges->leave($user, $challenge);

        return back();
    }
}
