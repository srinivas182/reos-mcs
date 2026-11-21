<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Authorization\Role;
use App\Connections\ConnectorRegistry;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\Integrations\IntegrationDetailResource;
use App\Http\Resources\Integrations\IntegrationSummaryResource;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use App\Models\AuditEvent;
use App\Models\CatalogueSetting;
use App\Models\Connection;
use App\Models\Organisation;
use App\Models\State;
use App\Models\SyncRun;
use App\Services\Catalogue\IntegrationCatalogue;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings › Integrations (super admin): the list and one integration's page. */
final class IntegrationController extends Controller
{
    public function index(Request $request, IntegrationHub $hub, IntegrationCatalogue $catalogue): Response
    {
        $settings = CatalogueSetting::query()->get()->keyBy('integration_id');
        $connected = $catalogue->connectedCounts();
        $demand = $catalogue->demand();

        return Inertia::render('admin/integrations/index', [
            'integrations' => array_map(
                static function (array $row) use ($request, $catalogue, $settings, $connected, $demand): array {
                    $definition = $row['definition'];
                    $setting = $settings->get($definition->id);

                    return [
                        ...(new IntegrationSummaryResource($definition, $row['status']))->resolve($request),
                        // Platform services aren't in the people's catalogue.
                        'catalogue' => $definition->kind !== 'integration' ? null : [
                            'status' => $catalogue->status($definition, $setting)->value,
                            'states' => $setting?->states,
                            'roles' => $setting?->roles,
                            'plans' => $setting?->plans,
                            'connected' => $connected[$definition->id] ?? 0,
                            'demand' => $demand[$definition->id] ?? 0,
                        ],
                    ];
                },
                $hub->overview(),
            ),
        ]);
    }

    public function show(Request $request, IntegrationDefinition $integration, IntegrationHub $hub, IntegrationCatalogue $catalogue, string $tab = 'availability'): Response
    {
        $status = $hub->status($integration);
        $credentials = [];
        $checks = [];

        foreach (CredentialEnvironment::cases() as $environment) {
            $credentials[$environment->value] = $hub->maskedCredentials($integration, $environment);
            $checks[$environment->value] = $hub->latestCheck($integration, $environment);
        }

        return Inertia::render('admin/integrations/show', [
            'integration' => (new IntegrationDetailResource(
                $integration,
                $status,
                $hub->modeAvailability($integration, $status),
                $credentials,
                $checks,
            ))->resolve($request),
            'tab' => $integration->kind === 'integration' || $tab !== 'availability' ? $tab : 'credentials',
            'catalogue' => $integration->kind !== 'integration' ? null : $this->catalogue($integration, $catalogue),
            'health' => $this->health($integration),
            'activity' => $hub->activity($integration)
                ->map(static fn (AuditEvent $event): array => (new AuditEventResource($event))->resolve($request))
                ->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function catalogue(IntegrationDefinition $integration, IntegrationCatalogue $catalogue): array
    {
        $setting = $catalogue->setting($integration->id);

        return [
            'status' => $setting->status?->value,
            'effective' => $catalogue->status($integration, $setting)->value,
            'default' => $integration->launch,
            'retires_on' => $setting->retires_on?->toDateString(),
            'states' => $setting->states,
            'roles' => $setting->roles,
            'plans' => $setting->plans,
            'beta_organisations' => $setting->beta_organisations,
            'tenants_may_hide' => $setting->tenants_may_hide,
            'tenants_may_require' => $setting->tenants_may_require,
            'show_coming_soon' => $setting->show_coming_soon,
            'built' => in_array($integration->id, ConnectorRegistry::ids(), true),
            // The journey's "Credentials and health" summary beside the rules.
            'summary' => [
                'auth' => $integration->auth->value,
                'accounts' => $integration->accounts->value,
                'rate_limit' => in_array($integration->id, ConnectorRegistry::ids(), true)
                    ? app(ConnectorRegistry::class)->requestsPerMinute($integration->id, app(IntegrationHub::class)->mode($integration))
                    : null,
            ],
            'demand' => $catalogue->demand()[$integration->id] ?? 0,
            'options' => [
                'states' => State::query()->orderBy('name')->get(['code', 'name'])->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->all(),
                'roles' => array_map(static fn (Role $r): string => $r->value, Role::cases()),
                'organisations' => Organisation::query()->where('type', '!=', OrganisationType::Platform)->orderBy('name')->get(['id', 'name'])
                    ->map(static fn (Organisation $o): array => ['id' => $o->id, 'name' => $o->name])->all(),
            ],
        ];
    }

    /**
     * Connections and sync health over the last 7 days.
     *
     * @return array{connected: int, reconnect: int, failing: int, runs: int, success: float|null}
     */
    private function health(IntegrationDefinition $integration): array
    {
        $statuses = Connection::query()->where('integration_id', $integration->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $count = static fn (mixed $n): int => is_numeric($n) ? intval($n) : 0;
        $runs = SyncRun::query()->whereIn('connection_id', Connection::query()->select('id')->where('integration_id', $integration->id))
            ->where('started_at', '>=', now()->subDays(7))->whereIn('outcome', [SyncRun::OUTCOME_SYNCED, SyncRun::OUTCOME_CONTINUED, SyncRun::OUTCOME_FAILED]);
        $total = (clone $runs)->count();
        $failed = (clone $runs)->where('outcome', SyncRun::OUTCOME_FAILED)->count();

        return [
            'connected' => array_sum(array_map($count, $statuses->all())),
            'reconnect' => $count($statuses->get('reconnect_needed')),
            'failing' => $count($statuses->get('failing')),
            'runs' => $total,
            'success' => $total === 0 ? null : round(100 * ($total - $failed) / $total, 2),
        ];
    }
}
