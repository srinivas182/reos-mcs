<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Agents\AgentModes;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rewards\PointRates;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Points (A13.3a): what each daily goal earns, the all-goals bonus, the
 * daily cap and REOS's targets per mode. 1,000 points = $10 inside REOS
 * (fixed), with no cash value.
 */
final class PointRatesController extends Controller
{
    public function show(PointRates $rates): Response
    {
        return Inertia::render('admin/points/index', ['rates' => $rates->current(), 'goals' => PointRates::GOALS, 'modes' => AgentModes::GOALS]);
    }

    public function update(Request $request, #[CurrentUser] User $actor, PointRates $rates): RedirectResponse
    {
        $rules = ['all_done' => ['required', 'integer', 'min:0', 'max:1000'], 'daily_cap' => ['required', 'integer', 'min:0', 'max:5000']];

        foreach (PointRates::GOALS as $goal) {
            $rules["points.{$goal}"] = ['required', 'integer', 'min:0', 'max:1000'];

            foreach (AgentModes::GOALS as $mode) {
                $rules["targets.{$mode}.{$goal}"] = ['required', 'integer', 'min:0', 'max:50'];
            }
        }

        $data = $request->validate($rules);
        $current = $rates->current();
        $points = is_array($data['points'] ?? null) ? $data['points'] : [];
        $targets = is_array($data['targets'] ?? null) ? $data['targets'] : [];

        foreach (PointRates::GOALS as $goal) {
            $current['points'][$goal] = is_numeric($points[$goal] ?? null) ? (int) $points[$goal] : 0;

            foreach (AgentModes::GOALS as $mode) {
                $forMode = is_array($targets[$mode] ?? null) ? $targets[$mode] : [];
                $current['targets'][$mode][$goal] = is_numeric($forMode[$goal] ?? null) ? (int) $forMode[$goal] : 0;
            }
        }
        $current['all_done'] = is_numeric($data['all_done'] ?? null) ? (int) $data['all_done'] : 0;
        $current['daily_cap'] = is_numeric($data['daily_cap'] ?? null) ? (int) $data['daily_cap'] : 0;
        $rates->save($current, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('rewards.admin.saved')]);

        return back();
    }
}
