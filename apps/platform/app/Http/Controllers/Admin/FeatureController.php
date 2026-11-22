<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrganisationType;
use App\Enums\StateStatus;
use App\Features\FlagCatalogue;
use App\Features\Flags;
use App\Features\FlagWatch;
use App\Features\Releases;
use App\Features\Rollouts;
use App\Features\RolloutStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Features\ChangeRolloutRequest;
use App\Http\Requests\Admin\Features\PublishReleaseRequest;
use App\Models\FeatureFlag;
use App\Models\Organisation;
use App\Models\Release;
use App\Models\State;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Features and releases (web journey screen 70, A6.2): every flag with its
 * rollout step, who sees it and errors since the rollout; change rollout,
 * turn off everywhere (kill switch) and back on; releases with notes per
 * role and the flags they roll out. Super admin only, every change audited.
 */
final class FeatureController extends Controller
{
    public function index(Flags $flags): Response
    {
        $all = $flags->all();
        $tenantIds = array_merge(...array_map(static fn (FeatureFlag $f): array => $f->tenant_ids ?? [], $all));
        $tenants = Organisation::query()->where('type', '!=', OrganisationType::Platform)->orderBy('name')->limit(500)->get(['id', 'name', 'type', 'states']);
        $names = Organisation::query()->whereIn('id', $tenantIds === [] ? [0] : $tenantIds)->get(['id', 'name'])->mapWithKeys(static fn (Organisation $o): array => [$o->id => $o->name])->all();

        return Inertia::render('admin/features/index', [
            'flags' => array_map(static function (FeatureFlag $flag) use ($names): array {
                $since = FlagWatch::sinceRollout($flag);

                return [
                    'key' => $flag->key,
                    'name' => $flag->name,
                    'description' => $flag->description,
                    'owner' => $flag->owner,
                    'tenant_feature' => $flag->tenant_feature,
                    'stage' => $flag->stage->value,
                    'tenants' => array_map(static fn (int $id): array => ['id' => $id, 'name' => $names[$id] ?? "#{$id}"], $flag->tenant_ids ?? []),
                    'states' => $flag->states ?? [],
                    'percent' => $flag->percent,
                    'roles' => $flag->roles,
                    'error_threshold' => $flag->error_threshold,
                    'stage_changed_at' => $flag->stage_changed_at?->toIso8601String(),
                    'killed_at' => $flag->killed_at?->toIso8601String(),
                    'rolled_back_at' => $flag->rolled_back_at?->toIso8601String(),
                    'rolled_back_from' => $flag->rolled_back_from?->value,
                    'uses' => $since['uses'],
                    'errors' => $since['errors'],
                ];
            }, $all),
            'releases' => Release::query()->withCount('reads')->with('author:id,name')->orderByDesc('released_on')->orderByDesc('id')->limit(20)->get()->map(static fn (Release $release): array => [
                'id' => $release->id,
                'version' => $release->version,
                'released_on' => $release->released_on->toDateString(),
                'notes' => $release->notes,
                'flags' => $release->flags ?? [],
                'author' => $release->author?->name,
                'dismissed' => $release->reads_count ?? 0,
            ])->all(),
            'options' => [
                'stages' => array_map(static fn (RolloutStage $s): string => $s->value, RolloutStage::cases()),
                'roles' => Flags::roles(),
                'states' => State::query()->where('status', StateStatus::Live)->orderBy('name')->get(['code', 'name'])->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->all(),
                'tenants' => $tenants->map(static fn (Organisation $t): array => ['id' => $t->id, 'name' => $t->name, 'type' => $t->type->value])->all(),
                'window_minutes' => FlagWatch::WINDOW_MINUTES,
            ],
        ]);
    }

    public function rollout(ChangeRolloutRequest $request, string $flag, Flags $flags, Rollouts $rollouts, #[CurrentUser] User $actor): RedirectResponse
    {
        $rollouts->change(self::find($flags, $flag), $request->rollout(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('features.rolloutSaved')]);

        return back();
    }

    public function kill(string $flag, Flags $flags, Rollouts $rollouts, #[CurrentUser] User $actor): RedirectResponse
    {
        // The page offers Undo (turn back on) instead of asking first.
        $rollouts->kill(self::find($flags, $flag), $actor);

        return back();
    }

    public function restore(string $flag, Flags $flags, Rollouts $rollouts, #[CurrentUser] User $actor): RedirectResponse
    {
        $found = self::find($flags, $flag);
        $rollouts->restore($found, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('features.restored', ['name' => $found->name])]);

        return back();
    }

    public function publish(PublishReleaseRequest $request, Releases $releases, #[CurrentUser] User $actor): RedirectResponse
    {
        $release = $releases->publish($request->release(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('features.releasePublished', ['version' => $release->version])]);

        return back();
    }

    /** The route only matches catalogue flags; this keeps a stray key a 404. */
    private static function find(Flags $flags, string $key): FeatureFlag
    {
        abort_unless(FlagCatalogue::has($key), 404);

        return $flags->flag($key);
    }
}
