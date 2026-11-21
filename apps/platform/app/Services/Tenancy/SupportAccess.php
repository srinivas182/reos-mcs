<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Notifications\SupportAccessRequested;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\SupportAccessStatus;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Support sign-in with consent (A4.1c, screen 60).
 *
 * REOS support asks with a reason; every owner of the tenant is emailed and
 * one of them approves or declines. Approved access starts when support
 * starts it and lasts 60 minutes. During it, support acts in the tenant's
 * admin role, inside that tenant only: it never sees anyone's personal
 * connections. A banner shows on every page, every page is logged in the
 * tenant's audit log, and the owner can end it at any time.
 */
final readonly class SupportAccess
{
    public const SESSION_KEY = 'support_grant';

    public const MINUTES = 60;

    /** How long the owner has to answer, and support has to start after a yes. */
    public const ANSWER_HOURS = 24;

    public const AUDIT_SUBJECT = 'support_access';

    public function __construct(
        private AuditLogger $audit,
        private Memberships $memberships,
        private Tenancy $tenancy,
    ) {}

    /** The admin role support works in for this kind of tenant. */
    public static function roleFor(OrganisationType $type): Role
    {
        return match ($type) {
            OrganisationType::Association => Role::AssociationAdmin,
            OrganisationType::School => Role::SchoolAdmin,
            OrganisationType::ProviderBusiness => Role::Provider,
            OrganisationType::Brokerage, OrganisationType::Team, OrganisationType::Platform => Role::Broker,
        };
    }

    /** @return list<User> The people who can say yes: everyone holding the tenant's admin role. */
    public function owners(Organisation $tenant): array
    {
        $role = self::roleFor($tenant->type);

        return array_values(array_filter(
            $this->memberships->holders($role)->all(),
            fn (User $user): bool => $this->memberships->find($user, $role, $tenant->id) !== null,
        ));
    }

    public function isOwner(User $user, Organisation $tenant): bool
    {
        return $this->memberships->find($user, self::roleFor($tenant->type), $tenant->id) !== null;
    }

    /** The latest request for a tenant, brought up to date (timeouts applied). */
    public function latest(Organisation $tenant): ?SupportAccessGrant
    {
        $grant = SupportAccessGrant::query()->where('organisation_id', $tenant->id)->latest('id')->first();

        return $grant === null ? null : $this->settle($grant);
    }

    public function ask(Organisation $tenant, User $actor, string $reason): SupportAccessGrant
    {
        $open = $this->latest($tenant);

        if ($open !== null && $open->status->open()) {
            throw ValidationException::withMessages(['reason' => __('support.errors.alreadyOpen')]);
        }

        $owners = $this->owners($tenant);

        if ($owners === []) {
            throw ValidationException::withMessages(['reason' => __('support.errors.noOwner')]);
        }

        $grant = SupportAccessGrant::query()->create([
            'organisation_id' => $tenant->id,
            'requested_by' => $actor->id,
            'reason' => $reason,
            'role' => self::roleFor($tenant->type),
            'status' => SupportAccessStatus::Pending,
        ]);

        $this->audit->record('support.requested', self::AUDIT_SUBJECT, (string) $grant->id, ['reason' => $reason], $actor, $tenant->id);
        Notification::send($owners, new SupportAccessRequested($grant));

        return $grant;
    }

    public function answer(SupportAccessGrant $grant, User $owner, bool $approve): SupportAccessGrant
    {
        $grant = $this->settle($grant);

        if ($grant->status !== SupportAccessStatus::Pending) {
            throw ValidationException::withMessages(['grant' => __('support.errors.notPending')]);
        }

        $grant->forceFill([
            'status' => $approve ? SupportAccessStatus::Approved : SupportAccessStatus::Declined,
            'decided_by' => $owner->id,
            'decided_at' => now(),
        ])->save();

        $this->audit->record($approve ? 'support.approved' : 'support.declined', self::AUDIT_SUBJECT, (string) $grant->id, [], $owner, $grant->organisation_id);

        return $grant;
    }

    /** Support starts the 60 minutes; this browser session now works inside the tenant. */
    public function start(SupportAccessGrant $grant, User $actor, Request $request): SupportAccessGrant
    {
        $grant = $this->settle($grant);

        if ($grant->status !== SupportAccessStatus::Approved || $grant->requested_by !== $actor->id) {
            throw ValidationException::withMessages(['grant' => __('support.errors.notApproved')]);
        }

        $grant->forceFill([
            'status' => SupportAccessStatus::Active,
            'started_at' => now(),
            'ends_at' => now()->addMinutes(self::MINUTES),
        ])->save();

        $request->session()->put(self::SESSION_KEY, $grant->id);
        $request->session()->regenerate();
        $this->audit->record('support.started', self::AUDIT_SUBJECT, (string) $grant->id, ['ends_at' => $grant->ends_at?->toIso8601String()], $actor, $grant->organisation_id);

        return $grant;
    }

    /**
     * End access: by support (`ended`), by the owner (`revoked`), or because
     * the time ran out (`expired`).
     */
    public function end(SupportAccessGrant $grant, SupportAccessStatus $how, ?User $actor): SupportAccessGrant
    {
        if (! in_array($grant->status, [SupportAccessStatus::Active, SupportAccessStatus::Approved, SupportAccessStatus::Pending], true)) {
            return $grant;
        }

        $grant->forceFill(['status' => $how, 'ended_at' => now()])->save();
        $this->audit->record('support.ended', self::AUDIT_SUBJECT, (string) $grant->id, ['how' => $how->value], $actor, $grant->organisation_id);

        return $grant;
    }

    /**
     * The support session this browser is in, if it's still valid. Read in
     * platform scope: it decides the tenant, so it runs before one is set.
     */
    public function current(User $user, Request $request): ?SupportAccessGrant
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);

        if (! is_int($id)) {
            return null;
        }

        $grant = $this->tenancy->asPlatform(fn (): ?SupportAccessGrant => SupportAccessGrant::query()->with('organisation')->find($id));

        if ($grant !== null) {
            $grant = $this->tenancy->asPlatform(fn (): SupportAccessGrant => $this->settle($grant));
        }

        if ($grant === null || $grant->status !== SupportAccessStatus::Active || $grant->requested_by !== $user->id) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $grant;
    }

    /** Apply timeouts: unanswered after 24 hours, unused after 24 hours, or 60 minutes up. */
    public function settle(SupportAccessGrant $grant): SupportAccessGrant
    {
        $expired = match ($grant->status) {
            SupportAccessStatus::Pending => $grant->created_at?->addHours(self::ANSWER_HOURS)->isPast() ?? false,
            SupportAccessStatus::Approved => $grant->decided_at?->addHours(self::ANSWER_HOURS)->isPast() ?? false,
            SupportAccessStatus::Active => $grant->ends_at?->isPast() ?? true,
            default => false,
        };

        if ($expired) {
            DB::transaction(fn () => $this->end($grant, SupportAccessStatus::Expired, null));
        }

        return $grant;
    }

    /** Every page support sees while inside, in the tenant's audit log. */
    public function logView(SupportAccessGrant $grant, User $actor, Request $request): void
    {
        $this->audit->record('support.viewed', self::AUDIT_SUBJECT, (string) $grant->id, [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
        ], $actor, $grant->organisation_id);
    }
}
