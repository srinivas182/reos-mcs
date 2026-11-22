<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Billing\Ledger\SponsorPools;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tenants\StoreTenantRequest;
use App\Http\Requests\Admin\Tenants\TenantStatusRequest;
use App\Http\Requests\Admin\Tenants\UpdateTenantRequest;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\Tenancy\TenantResource;
use App\Integrations\Catalogue;
use App\Models\AuditEvent;
use App\Models\Connection;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\SponsorCode;
use App\Models\State;
use App\Models\User;
use App\Services\Tenancy\SupportAccess;
use App\Services\Tenancy\Tenants;
use App\Tenancy\TenantFeature;
use App\Tenancy\TenantLimits;
use App\Tenancy\TenantPlan;
use App\Tenancy\TenantStatus;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Tenants (web journey screens 59 and 60): every organisation, and one tenant's settings. */
final class TenantController extends Controller
{
    public const TABS = ['overview', 'plan', 'features', 'integrations', 'domain', 'privacy', 'audit'];

    public function index(Request $request, Tenants $tenants): Response
    {
        $filters = self::filters($request);

        return Inertia::render('admin/tenants/index', [
            'filters' => $filters,
            'tenants' => TenantResource::collection($tenants->directory($filters))->resolve($request),
            'options' => self::options(),
        ]);
    }

    public function store(StoreTenantRequest $request, Tenants $tenants, #[CurrentUser] User $actor): RedirectResponse
    {
        $tenant = $tenants->create($request->tenant(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.created', ['name' => $tenant->name])]);

        return to_route('tenants.show', $tenant);
    }

    public function show(Request $request, Organisation $tenant, Tenants $tenants, Catalogue $register, SupportAccess $support, string $tab = 'overview'): Response
    {
        abort_if($tenant->type === OrganisationType::Platform, 404);
        $health = $tenants->health($tenant);
        $grant = $support->latest($tenant);
        $limits = $tenant->tenantLimits();

        return Inertia::render('admin/tenants/show', [
            'tab' => $tab,
            'tenant' => [
                ...(new TenantResource($tenant->loadMissing('parent')))->resolve($request),
                'members' => $health['members'],
                'slug' => $tenant->slug,
                'region' => $tenant->region,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
                'status_reason' => $tenant->status_reason,
                'owner' => $health['owner'] === null ? null : ['name' => $health['owner']->name, 'email' => $health['owner']->email],
                'last_active_at' => $health['last_active_at']?->toIso8601String(),
                'connection_problems' => $health['connection_problems'],
                'limits' => $limits->toArray(),
                'default_limits' => TenantLimits::defaults($tenant->type)->toArray(),
                'features' => array_map(static fn (TenantFeature $feature): array => [
                    'key' => $feature->value,
                    'on' => $tenant->hasFeature($feature),
                    'default' => $feature->defaultFor($tenant->type),
                    'availability' => $feature->availability(),
                ], TenantFeature::cases()),
                'children' => $tenant->children()->orderBy('name')->get(['id', 'name', 'type'])
                    ->map(static fn (Organisation $child): array => ['id' => $child->id, 'name' => $child->name, 'type' => $child->type->value])->all(),
                'hidden_tools' => DB::table('organisation_hidden_integrations')->where('organisation_id', $tenant->id)->orderBy('integration_id')->pluck('integration_id')
                    ->filter(static fn (mixed $id): bool => is_string($id))
                    ->map(static fn (string $id): array => ['id' => $id, 'name' => $register->find($id)->name ?? $id])->values()->all(),
                'connections' => Connection::query()->where('owner_type', Connection::OWNER_ORGANISATION)->where('owner_id', $tenant->id)->get()
                    ->map(static fn (Connection $c): array => ['id' => $c->id, 'name' => $register->find($c->integration_id)->name ?? $c->integration_id, 'account' => $c->account_label, 'status' => $c->status->value])->all(),
            ],
            'audit' => $tab === 'audit' ? AuditEvent::query()->with('actor')
                ->where(static fn (Builder $query) => $query->where('organisation_id', $tenant->id)
                    ->orWhere(static fn (Builder $q) => $q->where('subject_type', Tenants::AUDIT_SUBJECT)->where('subject_id', (string) $tenant->id)))
                ->latest('id')->limit(100)->get()
                ->map(static fn (AuditEvent $event): array => (new AuditEventResource($event))->resolve($request))->all() : [],
            // Support sign-in with consent (A4.1c): the latest request and whether anyone can approve.
            'support' => [
                'can_ask' => $support->owners($tenant) !== [],
                'latest' => $grant === null ? null : [
                    'status' => $grant->status->value,
                    'mine' => $grant->requested_by === $request->user()?->id,
                    'requester' => $grant->requester->name,
                    'decided_by' => $grant->decider?->name,
                    'requested_at' => $grant->created_at?->toIso8601String(),
                    'ends_at' => $grant->ends_at?->toIso8601String(),
                ],
            ],
            'options' => self::options(),
            // Sponsor codes on the Plan tab (B5.1b).
            'sponsor' => $tab === 'plan' ? [
                'codes' => SponsorCode::query()->with(['product:id,name', 'redemptions.user:id,name,email'])->where('sponsor_organisation_id', $tenant->id)->latest()->get()
                    ->map(static fn (SponsorCode $code): array => [
                        'id' => $code->id,
                        'code' => $code->code,
                        'product' => $code->product->name,
                        'state' => $code->state_code,
                        'seats' => $code->seats,
                        'used' => $code->used,
                        'access_months' => $code->access_months,
                        'expires_at' => $code->expires_at?->toIso8601String(),
                        'active' => $code->active,
                        'people' => $code->redemptions->map(static fn ($r): string => $r->user->name ?? $r->user->email ?? '')->values()->all(),
                    ])->values()->all(),
                'products' => Product::query()->where('status', 'live')->orderBy('name')->get(['id', 'name'])
                    ->map(static fn (Product $p): array => ['id' => $p->id, 'name' => $p->name])->values()->all(),
                // Sponsor pools (B5.3).
                'pools' => app(SponsorPools::class)->balances($tenant),
            ] : null,
        ]);
    }

    public function update(UpdateTenantRequest $request, Organisation $tenant, Tenants $tenants, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_if($tenant->type === OrganisationType::Platform, 404);
        $tenants->update($tenant, $request->changes(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.saved', ['name' => $tenant->name])]);

        return back();
    }

    public function status(TenantStatusRequest $request, Organisation $tenant, Tenants $tenants, #[CurrentUser] User $actor): RedirectResponse
    {
        abort_if($tenant->type === OrganisationType::Platform, 404);
        $tenants->setStatus($tenant, $request->status(), $request->reason(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.statusChanged.'.$request->status()->value, ['name' => $tenant->name])]);

        return back();
    }

    /** The table as CSV, with the filters on screen. */
    public function export(Request $request, Tenants $tenants): StreamedResponse
    {
        $rows = TenantResource::collection($tenants->directory(self::filters($request)))->resolve($request);

        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['id', 'name', 'type', 'parent', 'states', 'plan', 'seats', 'members', 'domain', 'cell', 'status'], escape: '');
            foreach ($rows as $row) {
                /** @var array<string, mixed> $row */
                fputcsv($out, array_map(
                    static fn (mixed $value): string => self::csvCell(is_array($value) ? implode(' ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value)) : (is_scalar($value) ? (string) $value : '')),
                    [$row['id'], $row['name'], $row['type'], $row['parent'], $row['states'], $row['plan'], $row['seats'], $row['members'], $row['domain'], $row['cell'], $row['status']],
                ), escape: '');
            }
            fclose($out);
        }, 'reos-tenants-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Spreadsheet formula injection: a leading =, +, - or @ is made plain text. */
    private static function csvCell(string $value): string
    {
        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }

    /** @return array{type: string|null, status: string|null, state: string|null, search: string|null} */
    private static function filters(Request $request): array
    {
        $pick = static fn (string $key, array $allowed): ?string => in_array($request->query($key), $allowed, true) ? (string) $request->query($key) : null;

        return [
            'type' => $pick('type', array_map(static fn (OrganisationType $t): string => $t->value, OrganisationType::cases())),
            'status' => $pick('status', array_map(static fn (TenantStatus $s): string => $s->value, TenantStatus::cases())),
            'state' => $pick('state', State::query()->pluck('code')->filter(static fn (mixed $c): bool => is_string($c))->values()->all()),
            'search' => $request->filled('search') ? $request->string('search')->trim()->limit(100, '')->toString() : null,
        ];
    }

    /** @return array<string, mixed> Choices for filters and forms. */
    private static function options(): array
    {
        return [
            'types' => array_values(array_map(static fn (OrganisationType $t): string => $t->value, array_filter(OrganisationType::cases(), static fn (OrganisationType $t): bool => $t !== OrganisationType::Platform))),
            'statuses' => array_map(static fn (TenantStatus $s): string => $s->value, TenantStatus::cases()),
            'plans' => array_map(static fn (TenantPlan $p): string => $p->value, TenantPlan::cases()),
            'states' => State::query()->orderBy('name')->get(['code', 'name'])->map(static fn (State $s): array => ['code' => $s->code, 'name' => $s->name])->all(),
            'brokerages' => Organisation::query()->where('type', OrganisationType::Brokerage->value)->orderBy('name')->get(['id', 'name'])
                ->map(static fn (Organisation $o): array => ['id' => $o->id, 'name' => $o->name])->all(),
        ];
    }
}
