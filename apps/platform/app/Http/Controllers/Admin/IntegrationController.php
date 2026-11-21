<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\Integrations\IntegrationDetailResource;
use App\Http\Resources\Integrations\IntegrationSummaryResource;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use App\Models\AuditEvent;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings › Integrations (super admin): the list and one integration's page. */
final class IntegrationController extends Controller
{
    public function index(Request $request, IntegrationHub $hub): Response
    {
        return Inertia::render('admin/integrations/index', [
            'integrations' => array_map(
                static fn (array $row): array => (new IntegrationSummaryResource($row['definition'], $row['status']))->resolve($request),
                $hub->overview(),
            ),
        ]);
    }

    public function show(Request $request, IntegrationDefinition $integration, IntegrationHub $hub): Response
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
            'activity' => $hub->activity($integration)
                ->map(static fn (AuditEvent $event): array => (new AuditEventResource($event))->resolve($request))
                ->all(),
        ]);
    }
}
