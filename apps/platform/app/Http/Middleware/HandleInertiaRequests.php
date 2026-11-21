<?php

namespace App\Http\Middleware;

use App\Authorization\Membership;
use App\Authorization\Permission;
use App\Enums\StateStatus;
use App\Models\State;
use App\Models\User;
use App\Monitoring\MonitoringRuntime;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // People who signed up with a phone, Google or Apple have no password.
                'hasPassword' => $request->user()?->password !== null,
                'can' => [
                    'manageIntegrations' => $request->user()?->can(Permission::IntegrationsManage->value) ?? false,
                    'managePeople' => $request->user()?->can(Permission::PeopleManage->value) ?? false,
                ],
                // Roles held, the one in use on this device, and what it allows (A3.2).
                'roles' => fn () => $this->roles($request),
            ],
            // The state chip in the top bar. Texas only for now; A5.1 adds the state switcher.
            'region' => $request->user() === null ? null : [
                'code' => config()->string('reos.region.code'),
                'name' => config()->string('reos.region.name'),
            ],
            'preferences' => [
                'appearance' => View::shared('appearance', 'system'),
                'textSize' => View::shared('textSize', 'standard'),
                'locale' => app()->getLocale(),
            ],
            // Public settings for browser error tracking and analytics (2.4c).
            'monitoring' => fn () => app(MonitoringRuntime::class)->clientConfig('web'),
            // The public site's state chip and headline ("Now live in Texas").
            'liveStates' => fn (): array => State::query()->where('status', StateStatus::Live)->orderBy('name')->pluck('name')->all(),
            // One-time notices after a redirect (for example "You're on the list").
            'flash' => fn (): array => ['joined' => $request->hasSession() && $request->session()->get('joined') === true],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{held: list<array{role: string, organisation: array{id: int, name: string, type: string}, admin: bool}>, active: array{role: string, organisationId: int}|null, permissions: list<string>, twoStepEnabled: bool, twoStepRequired: bool}|null
     */
    private function roles(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $active = app(ActiveRole::class);
        $current = $active->for($user);

        return [
            'held' => array_map(static fn (Membership $m): array => [
                'role' => $m->role->value,
                'organisation' => ['id' => $m->organisation->id, 'name' => $m->organisation->name, 'type' => $m->organisation->type->value],
                'admin' => $m->role->isAdmin(),
            ], app(Memberships::class)->of($user)),
            'active' => $current === null ? null : ['role' => $current->role->value, 'organisationId' => $current->organisation->id],
            'permissions' => $active->permissions($user),
            'twoStepEnabled' => ActiveRole::hasTwoStep($user),
            'twoStepRequired' => $active->needsTwoStep($user),
        ];
    }
}
