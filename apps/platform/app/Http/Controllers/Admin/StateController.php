<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PrepareStateRequest;
use App\Http\Requests\Admin\StateChecklistRequest;
use App\Http\Requests\Admin\StateStatusRequest;
use App\Models\State;
use App\Models\User;
use App\Services\Onboarding\States;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * States (web journey screen 61): launch status, how many are waiting, and
 * the launch checklist a state must complete before it goes live (A5.1).
 */
final class StateController extends Controller
{
    public function index(States $states): Response
    {
        $waiting = $states->waiting();
        $rank = array_flip(array_map(static fn (StateStatus $status): string => $status->value, StateStatus::cases()));

        // Live, then in preparation, then the longest waitlists first.
        $sorted = $states->all()->sortBy([
            static fn (State $a, State $b): int => $rank[$a->status->value] <=> $rank[$b->status->value],
            static fn (State $a, State $b): int => ($waiting[$b->code] ?? 0) <=> ($waiting[$a->code] ?? 0),
            static fn (State $a, State $b): int => $a->name <=> $b->name,
        ])->values();

        return Inertia::render('admin/states/index', [
            'states' => $sorted->map(static fn (State $state): array => [
                'code' => $state->code,
                'name' => $state->name,
                'status' => $state->status->value,
                'regulator' => $state->regulator,
                'exam_vendor' => $state->exam_vendor,
                'qualifying_hours' => $state->qualifying_hours,
                'ce_hours' => $state->ce_hours,
                'waiting' => $waiting[$state->code] ?? 0,
                // Course approvals arrive with schools (Sprint 7); 0 until then.
                'courses_approved' => 0,
                'checklist' => array_map(static fn (?array $tick): bool => $tick !== null, States::checklist($state)),
                'went_live_at' => $state->went_live_at?->toIso8601String(),
            ])->all(),
            'statuses' => array_map(static fn (StateStatus $status): string => $status->value, StateStatus::cases()),
            'checklistItems' => States::CHECKLIST,
        ]);
    }

    /** "Add state": start preparing a state. */
    public function prepare(PrepareStateRequest $request, State $state, #[CurrentUser] User $actor, States $states): RedirectResponse
    {
        $states->prepare(
            $state,
            $request->string('regulator')->trim()->toString(),
            $request->optionalString('exam_vendor'),
            $request->optionalInt('qualifying_hours'),
            $request->optionalInt('ce_hours'),
            $actor,
        );

        return back();
    }

    /** Tick or untick a launch checklist item. */
    public function checklist(StateChecklistRequest $request, State $state, #[CurrentUser] User $actor, States $states): RedirectResponse
    {
        $states->tick($state, $request->string('item')->toString(), $request->boolean('done'), $actor);

        return back();
    }

    public function update(StateStatusRequest $request, State $state, #[CurrentUser] User $actor, States $states): RedirectResponse
    {
        $states->setStatus($state, $request->status(), $actor);

        return back();
    }
}
