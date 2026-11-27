<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rewards;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Mentoring\MentorDirectory;
use App\Models\User;
use App\Rewards\BonusCampaigns;
use App\Rewards\DailyGoals;
use App\Rewards\PointRates;
use App\Rewards\Rewards;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Goals and rewards on the web (A13.3a): the agent's daily goals, streak
 * and rewards; the broker's targets per mode and bonus campaigns.
 */
final class RewardsPageController extends Controller
{
    public function agent(#[CurrentUser] User $user, DailyGoals $goals, Rewards $rewards): Response
    {
        return Inertia::render('rewards/index', ['today' => $goals->today($user), 'rewards' => $rewards->summary($user)]);
    }

    public function redeem(Request $request, #[CurrentUser] User $user, Rewards $rewards): RedirectResponse
    {
        $request->validate(['price' => ['required', 'integer']]);
        $rewards->redeem($user, $request->integer('price'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('rewards.redeemed')]);

        return back();
    }

    public function broker(#[CurrentUser] User $user, MentorDirectory $directory, DailyGoals $goals, BonusCampaigns $campaigns): Response
    {
        $brokerage = $directory->brokerageOf($user, Role::Broker) ?? throw new NotFoundHttpException;

        return Inertia::render('organisation/rewards', [
            'brokerage' => $brokerage->name,
            'goals' => PointRates::GOALS,
            'modes' => $goals->brokerageTargets($brokerage),
            'campaigns' => $campaigns->list($brokerage),
            'multipliers' => BonusCampaigns::MULTIPLIERS,
        ]);
    }

    public function targets(Request $request, string $mode, #[CurrentUser] User $user, DailyGoals $goals): RedirectResponse
    {
        $rules = [];

        foreach (PointRates::GOALS as $goal) {
            $rules["targets.{$goal}"] = ['required', 'integer', 'min:0', 'max:50'];
        }
        $data = $request->validate($rules);
        /** @var array<string, mixed> $raw */
        $raw = is_array($data['targets'] ?? null) ? $data['targets'] : [];
        $targets = [];

        foreach (PointRates::GOALS as $goal) {
            $targets[$goal] = is_numeric($raw[$goal] ?? null) ? (int) $raw[$goal] : 0;
        }
        $goals->saveTargets($user, $mode, $targets);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('rewards.broker.saved')]);

        return back();
    }

    public function campaign(Request $request, #[CurrentUser] User $user, BonusCampaigns $campaigns): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'goal' => ['required', Rule::in([...PointRates::GOALS, 'all'])],
            'multiplier' => ['required', 'integer', Rule::in(BonusCampaigns::MULTIPLIERS)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'budget' => ['required', 'integer', 'min:100', 'max:10000000'],
        ]);
        $campaigns->create($user, $request->string('title')->toString(), $request->string('goal')->toString(), $request->integer('multiplier'), $request->string('starts_on')->toString(), $request->string('ends_on')->toString(), $request->integer('budget'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('rewards.campaigns.created')]);

        return back();
    }

    public function endCampaign(string $campaign, #[CurrentUser] User $user, BonusCampaigns $campaigns): RedirectResponse
    {
        $campaigns->end($user, $campaign);

        return back();
    }
}
