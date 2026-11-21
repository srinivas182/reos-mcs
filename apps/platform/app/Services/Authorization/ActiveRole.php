<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Authorization\Membership;
use App\Authorization\Permission;
use App\Authorization\Role;
use App\Authorization\RoleRefused;
use App\Identity\WebDevice;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Tenancy\SupportAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The role a person is using right now, which decides what they can do.
 *
 * Each device remembers its last role (`known_devices.active_role`), so a
 * broker who is also an instructor lands where they left off on each
 * device. A device with no choice yet takes the role last chosen anywhere,
 * then the first role held. Admin roles grant nothing until two-step (an
 * authenticator app or a passkey) is set up.
 *
 * Scoped: one instance per request (and per Octane request).
 */
final class ActiveRole
{
    /** @var array<string, Membership|null> */
    private array $resolved = [];

    public function __construct(private readonly Memberships $memberships) {}

    public function for(User $user): ?Membership
    {
        $key = $this->key($user);

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        // REOS support inside a tenant with the owner's consent (A4.1c): the
        // tenant's admin role there, for the 60 minutes only.
        $support = app(SupportAccess::class)->current($user, request());

        if ($support !== null) {
            return $this->resolved[$key] = new Membership($support->role, $support->organisation);
        }

        $held = $this->memberships->of($user);
        $device = $this->deviceId($user);

        $choices = DB::table('known_devices')
            ->where('user_id', $user->id)
            ->whereNotNull('active_role')
            // This device's choice first, then the latest anywhere.
            ->when($device !== null, fn ($query) => $query->orderByRaw('CASE WHEN device_id = ? THEN 0 ELSE 1 END', [$device]))
            ->orderByDesc('role_chosen_at')
            ->get(['active_role', 'active_organisation_id']);

        foreach ($choices as $choice) {
            foreach ($held as $membership) {
                if ($membership->role->value === $choice->active_role && $membership->organisation->id === $choice->active_organisation_id) {
                    return $this->resolved[$key] = $membership;
                }
            }
        }

        return $this->resolved[$key] = $held[0] ?? null;
    }

    /** Switch to another role the person holds; remembered on this device. */
    public function choose(User $user, Role $role, int $organisationId): Membership
    {
        $membership = $this->memberships->find($user, $role, $organisationId) ?? throw RoleRefused::notHeld();
        $device = $this->deviceId($user);

        if ($device !== null) {
            DB::table('known_devices')
                ->where('user_id', $user->id)
                ->where('device_id', $device)
                ->update([
                    'active_role' => $role->value,
                    'active_organisation_id' => $organisationId,
                    'role_chosen_at' => now(),
                ]);
        }

        return $this->resolved[$this->key($user)] = $membership;
    }

    /**
     * What the person can do in their active role. Admin roles need two-step.
     */
    public function allows(User $user, Permission $permission): bool
    {
        $membership = $this->for($user);

        return $membership !== null
            && $membership->role->allows($permission)
            && ! $this->blocked($user, $membership);
    }

    /** @return list<string> */
    public function permissions(User $user): array
    {
        $membership = $this->for($user);

        if ($membership === null || $this->blocked($user, $membership)) {
            return [];
        }

        return array_map(static fn (Permission $permission): string => $permission->value, $membership->role->permissions());
    }

    /** The active role is an admin role and two-step isn't set up yet. */
    public function needsTwoStep(User $user): bool
    {
        $membership = $this->for($user);

        return $membership !== null && $this->blocked($user, $membership);
    }

    public static function hasTwoStep(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null || $user->passkeys()->exists();
    }

    /** Forget what this request resolved, after a role is granted or removed. */
    public function forget(User $user): void
    {
        unset($this->resolved[$this->key($user)]);
    }

    /** Answers are kept for one person within one request. */
    private function key(User $user): string
    {
        return $user->id.':'.spl_object_id(request());
    }

    private function blocked(User $user, Membership $membership): bool
    {
        return $membership->role->isAdmin() && ! self::hasTwoStep($user);
    }

    /** The phone app's device (from its token) or the browser's cookie. */
    private function deviceId(User $user): ?string
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            return $token->device_id;
        }

        $cookie = request()->cookie(WebDevice::COOKIE);

        return is_string($cookie) && Str::isUuid($cookie) ? $cookie : null;
    }
}
