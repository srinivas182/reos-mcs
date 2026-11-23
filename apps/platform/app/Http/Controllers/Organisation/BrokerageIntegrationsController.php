<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Crm\BrokerageIntegrations;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\AllowIntegrationRequest;
use App\Http\Requests\Organisation\CrmMappingRequest;
use App\Models\Connection;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Catalogue\IntegrationCatalogue;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Brokerage integrations (broker web screen 40, B8.2): which REOS tools the
 * brokerage's agents may use, the brokerage's own accounts (Salesforce),
 * agents connected, and for a brokerage CRM its sync health and stage
 * mapping. Brokerage admins only; brokerages can only hide tools.
 */
final class BrokerageIntegrationsController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles, BrokerageIntegrations $integrations): Response
    {
        $brokerage = $this->brokerage($user, $roles);
        $rows = $integrations->rows($brokerage);
        $fields = [];

        foreach ($rows as $row) {
            if ($row['account'] !== null && $row['account']['mapping'] !== null) {
                $connection = Connection::query()->find($row['account']['connection']);
                $fields[$row['id']] = $connection === null ? null : $integrations->stageFields($connection);
            }
        }

        return Inertia::render('organisation/integrations', ['brokerage' => ['id' => $brokerage->id, 'name' => $brokerage->name], 'tools' => $rows, 'stage_fields' => $fields]);
    }

    public function allow(AllowIntegrationRequest $request, string $tool, #[CurrentUser] User $user, ActiveRole $roles, BrokerageIntegrations $integrations, IntegrationCatalogue $catalogue): RedirectResponse
    {
        $brokerage = $this->brokerage($user, $roles);
        abort_unless(collect($catalogue->forPeople())->contains('id', $tool), 404);
        $allowed = $request->allowed();
        $integrations->allow($brokerage, $tool, $allowed, $user);

        return back()->with('success', __($allowed ? 'brokerageIntegrations.allowed' : 'brokerageIntegrations.hidden'));
    }

    public function mapping(CrmMappingRequest $request, int $connection, #[CurrentUser] User $user, ActiveRole $roles, BrokerageIntegrations $integrations): RedirectResponse
    {
        $brokerage = $this->brokerage($user, $roles);
        $found = Connection::query()->where('owner_type', Connection::OWNER_ORGANISATION)->where('owner_id', $brokerage->id)->findOrFail($connection);
        $integrations->map($found, $request->mapping(), $user);

        return back()->with('success', __('brokerageIntegrations.mapped'));
    }

    private function brokerage(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && in_array($membership->organisation->type, [OrganisationType::Brokerage], true), 404);

        return $membership->organisation;
    }
}
