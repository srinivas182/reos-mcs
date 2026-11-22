<?php

declare(strict_types=1);

namespace App\Billing;

use App\Content\CourseType;
use App\Models\Entitlement;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Who may use what (B5.1, ADR 0021). REOS's own record, fed by orders,
 * subscriptions, sponsors and admins from any gateway, so the web and the
 * phone ask one place. Course access is `learn.<course type>` in the
 * course's state (or every state).
 */
final class Entitlements
{
    /**
     * Active features for the person, optionally in one state.
     *
     * @return list<string>
     */
    public function features(User $user, ?string $state = null): array
    {
        return array_values(array_unique(Entitlement::query()
            ->active()
            ->where('user_id', $user->id)
            ->when($state !== null, static fn ($q) => $q->where(static fn ($q) => $q->whereNull('state_code')->orWhere('state_code', $state)))
            ->orderBy('feature')
            ->pluck('feature')
            ->map(static fn (mixed $feature): string => is_string($feature) ? $feature : '')
            ->filter()
            ->all()));
    }

    public function has(User $user, string $feature, ?string $state = null): bool
    {
        return in_array($feature, $this->features($user, $state), true);
    }

    public static function courseFeature(CourseType $type): string
    {
        return 'learn.'.$type->value;
    }

    /** @param  list<string>  $features */
    public function grant(User $user, array $features, EntitlementSource $source, ?int $sourceId, ?string $state, ?CarbonInterface $endsAt = null): void
    {
        foreach (array_unique($features) as $feature) {
            $existing = Entitlement::query()
                ->where('user_id', $user->id)
                ->where('feature', $feature)
                ->where('source', $source)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing instanceof Entitlement) {
                $existing->forceFill(['ends_at' => $endsAt, 'revoked_at' => null])->save();

                continue;
            }

            Entitlement::query()->create([
                'user_id' => $user->id,
                'feature' => $feature,
                'source' => $source,
                'source_id' => $sourceId,
                'state_code' => $state,
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);
        }
    }

    /** Moves the end of everything from one source (a renewal, a grace period, a cancellation). */
    public function extend(EntitlementSource $source, int $sourceId, ?CarbonInterface $endsAt): void
    {
        Entitlement::query()->where('source', $source)->where('source_id', $sourceId)->whereNull('revoked_at')->update(['ends_at' => $endsAt]);
    }

    /** Ends everything from one source at once (a refund). */
    public function revoke(EntitlementSource $source, int $sourceId): void
    {
        Entitlement::query()->where('source', $source)->where('source_id', $sourceId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
