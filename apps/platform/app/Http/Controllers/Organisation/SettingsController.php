<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Authorization\Role;
use App\Brokerage\BrokerageSeats;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenancy\TenantDomainResource;
use App\Http\Resources\Tenancy\TenantResource;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\Invitations;
use App\Services\Tenancy\TenantDomains;
use App\Tenancy\Brand;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Brokerage settings (broker, web journey screen 41): the brokerage's own
 * domain, its brand (decision of 21 November 2026), the people who run it
 * with their roles and two-step status, and its plan. Inviting admins and
 * changing permissions arrive with A4.3.
 */
final class SettingsController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, ActiveRole $activeRole, TenantDomains $domains, Invitations $invitations): Response
    {
        $membership = $activeRole->for($user);
        abort_if($membership === null || in_array($membership->organisation->type, [OrganisationType::Platform, OrganisationType::Team], true), 404);
        $tenant = $membership->organisation;
        $domain = $domains->of($tenant);
        $limits = $tenant->tenantLimits();

        return Inertia::render('organisation/settings', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'type' => $tenant->type->value,
                'address' => $tenant->slug.'.'.config()->string('reos.tenancy.base_domain'),
                'live_address' => TenantResource::domain($tenant),
                'seats' => $limits->seats,
                'payer' => $limits->payer,
            ],
            'domain' => $domain === null ? null : (new TenantDomainResource($domain))->resolve($request),
            'brand' => Brand::of($tenant)->toArray(),
            'people' => self::people($tenant),
            // "Invite admins" (A4.3a).
            'invite' => $invitations->options($tenant),
            // Seats and who pays (A15.1b, broker journey screen 4): brokerages only.
            'seatsPlan' => $tenant->type === OrganisationType::Brokerage ? app(BrokerageSeats::class)->show($tenant) : null,
        ]);
    }

    /**
     * Everyone with an admin, team leader or mentor role in the brokerage
     * or its teams, with whether two-step is on.
     *
     * @return list<array{id: int, name: string, role: string, scope: string|null, two_step: bool}>
     */
    private static function people(Organisation $tenant): array
    {
        $teams = $tenant->children()->pluck('name', 'id');
        $roles = [Role::Broker->value, Role::Mentor->value, Role::SchoolAdmin->value, Role::AssociationAdmin->value, Role::Instructor->value, Role::Provider->value];

        $rows = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.organisation_id', [$tenant->id, ...$teams->keys()->all()])
            ->whereIn('roles.name', $roles)
            ->orderBy('model_has_roles.model_id')
            ->limit(100)
            ->get(['model_has_roles.model_id', 'model_has_roles.organisation_id', 'roles.name']);

        $users = User::query()->whereIn('id', $rows->pluck('model_id')->unique()->all())->get()->keyBy('id');
        $people = [];

        foreach ($rows as $row) {
            $person = $users->get($row->model_id);

            if ($person instanceof User && is_string($row->name)) {
                $people[] = [
                    'id' => $person->id,
                    'name' => (string) $person->name,
                    'role' => $row->name,
                    'scope' => $row->organisation_id === $tenant->id ? null : (is_string($teams[$row->organisation_id] ?? null) ? $teams[$row->organisation_id] : null),
                    'two_step' => ActiveRole::hasTwoStep($person),
                ];
            }
        }

        return $people;
    }
}
