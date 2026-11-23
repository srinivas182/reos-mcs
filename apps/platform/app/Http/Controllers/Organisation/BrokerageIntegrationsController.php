<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Connections\ConnectorRegistry;
use App\Connections\HistoryRange;
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
use App\Services\Connections\ConnectionAuthorizer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    /**
     * Connect a tool the brokerage reaches with its own ID or key (Lone
     * Wolf's client ID, B9.1b): checked with the tool at once, then synced.
     */
    public function connectKey(Request $request, string $tool, #[CurrentUser] User $user, ActiveRole $roles, ConnectionAuthorizer $authorizer, IntegrationCatalogue $catalogue): RedirectResponse
    {
        $brokerage = $this->brokerage($user, $roles);
        abort_unless(ConnectorRegistry::usesApiKey($tool) && ConnectorRegistry::ownsPerRecord($tool) && $catalogue->canConnect($user, $tool), 404);
        $secret = ConnectorRegistry::usesKeyAndSecret($tool);
        $input = $request->validate([
            'api_key' => ['required', 'string', 'min:4', 'max:200', ...($secret ? ['not_regex:/:/'] : [])],
            'api_secret' => [$secret ? 'required' : 'prohibited', 'string', 'min:4', 'max:200'],
        ]);
        $key = $secret && is_string($input['api_key']) && is_string($input['api_secret']) ? $input['api_key'].':'.$input['api_secret'] : $input['api_key'];

        $result = $authorizer->connectWithKey($user, $tool, Connection::OWNER_ORGANISATION, $brokerage->id, is_string($key) ? $key : '', HistoryRange::DEFAULT);

        if ($result['connection'] === null) {
            throw ValidationException::withMessages(['api_key' => __((string) $result['reason'])]);
        }

        return back()->with('success', __('brokerageIntegrations.connected'));
    }

    private function brokerage(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && in_array($membership->organisation->type, [OrganisationType::Brokerage], true), 404);

        return $membership->organisation;
    }
}
