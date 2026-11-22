<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StateRuleDraftRequest;
use App\Models\State;
use App\Models\StateRule;
use App\Models\User;
use App\Services\StateRules\StateRules;
use App\StateRules\RuleCatalogue;
use App\StateRules\RuleTopic;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * State rules (web journey screen 62, A5.2): a state's regulator rules in
 * tabs, where each is used, its source and effective date; edit as drafts,
 * publish them together, and see every version.
 */
final class StateRuleController extends Controller
{
    public function show(State $state, StateRules $rules): Response
    {
        $today = CarbonImmutable::today();
        $inForce = $rules->inForce($state->code, $today);
        $versions = $rules->versions($state->code);

        return Inertia::render('admin/states/rules', [
            'state' => ['code' => $state->code, 'name' => $state->name, 'regulator' => $state->regulator],
            'topics' => array_map(static fn (RuleTopic $topic): string => $topic->value, RuleTopic::cases()),
            'liveFeatures' => RuleCatalogue::LIVE_FEATURES,
            'rules' => array_map(static function (string $key, array $definition) use ($inForce, $versions, $today): array {
                $all = $versions->where('key', $key)->values();
                $draft = $all->firstWhere('status', StateRule::DRAFT);
                $scheduled = $all->first(static fn (StateRule $r): bool => $r->status === StateRule::PUBLISHED && $r->effective_from->isAfter($today));

                return [
                    'key' => $key,
                    'topic' => $definition['topic']->value,
                    'type' => $definition['type']->value,
                    'unit' => $definition['unit'] ?? null,
                    'used_by' => $definition['used_by'],
                    'current' => isset($inForce[$key]) ? self::rule($inForce[$key]) : null,
                    'draft' => $draft instanceof StateRule ? self::rule($draft) : null,
                    'scheduled' => $scheduled instanceof StateRule ? self::rule($scheduled) : null,
                    'versions' => $all->where('status', '!=', StateRule::DRAFT)->map(static fn (StateRule $r): array => self::rule($r))->values()->all(),
                ];
            }, array_keys(RuleCatalogue::all()), RuleCatalogue::all()),
        ]);
    }

    /** Save a change as a draft. */
    public function draft(StateRuleDraftRequest $request, State $state, string $key, #[CurrentUser] User $actor, StateRules $rules): RedirectResponse
    {
        $rules->draft($state, $key, $request->all(['value', 'citation', 'source_url', 'effective_from', 'note']), $actor);

        return back();
    }

    /** Throw a draft away. */
    public function discard(State $state, StateRule $rule, #[CurrentUser] User $actor, StateRules $rules): RedirectResponse
    {
        abort_unless($rule->state_code === $state->code, 404);
        $rules->discard($rule, $actor);

        return back();
    }

    /** "Publish changes": every draft becomes a new version. */
    public function publish(State $state, #[CurrentUser] User $actor, StateRules $rules): RedirectResponse
    {
        $count = $rules->publish($state, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('stateRules.published', ['count' => $count, 'state' => $state->name])]);

        return back();
    }

    /** @return array<string, mixed> */
    private static function rule(StateRule $rule): array
    {
        return [
            'id' => $rule->id,
            'version' => $rule->version,
            'status' => $rule->status,
            'value' => $rule->value,
            'citation' => $rule->citation,
            'source_url' => $rule->source_url,
            'effective_from' => $rule->effective_from->toDateString(),
            'checked_on' => $rule->checked_on?->toDateString(),
            'note' => $rule->note,
            'published_at' => $rule->published_at?->toIso8601String(),
            'published_by' => $rule->publisher?->name,
        ];
    }
}
