<?php

declare(strict_types=1);

namespace App\ProviderNetwork;

use App\Models\ProviderProfile;
use App\Models\ProviderServiceArea;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Where a provider works (B15.2, provider screen 4): cities, ZIP codes and
 * a radius around a ZIP code, which always work without a map (decision 6).
 * For each area REOS shows how many agents are active there (listing agents
 * on the MLS in the last 12 months) and how many providers in the same
 * category already serve it.
 *
 * @phpstan-type AreaRow array{id: int, kind: 'city'|'zip'|'radius', city: string|null, postal_code: string|null, radius_miles: int|null, agents: int, providers: int}
 */
final readonly class ServiceAreas
{
    public const MAX = ProviderPlans::PARTNER_AREAS;

    /** @return list<AreaRow> */
    public function of(ProviderProfile $profile): array
    {
        $rows = [];

        foreach ($profile->areas()->orderBy('id')->get() as $area) {
            $rows[] = $this->row($profile, $area);
        }

        return $rows;
    }

    /** @param  'city'|'zip'|'radius'  $kind */
    public function add(ProviderProfile $profile, string $kind, ?string $city, ?string $postalCode, ?int $radius): void
    {
        if ($profile->areas()->count() >= self::MAX) {
            throw ValidationException::withMessages(['area' => __('providers.onboarding.areas.errors.max', ['count' => self::MAX])]);
        }

        $city = $kind === 'city' ? trim((string) $city) : null;
        $zip = $kind === 'city' ? null : substr(preg_replace('/\D/', '', (string) $postalCode) ?? '', 0, 5);

        if ($kind === 'city' && mb_strlen((string) $city) < 2) {
            throw ValidationException::withMessages(['city' => __('providers.onboarding.areas.errors.city')]);
        }

        if ($kind !== 'city' && strlen((string) $zip) !== 5) {
            throw ValidationException::withMessages(['postal_code' => __('providers.onboarding.areas.errors.zip')]);
        }

        $same = $profile->areas()->where('kind', $kind)->when($kind === 'city', static fn ($q) => $q->whereRaw('lower(city) = ?', [mb_strtolower((string) $city)]), static fn ($q) => $q->where('postal_code', $zip))->exists();

        if ($same && $kind !== 'radius') {
            throw ValidationException::withMessages(['area' => __('providers.onboarding.areas.errors.duplicate')]);
        }

        $profile->areas()->create(['kind' => $kind, 'city' => $city, 'postal_code' => $zip, 'radius_miles' => $kind === 'radius' ? max(1, min(50, (int) $radius)) : null]);
    }

    public function remove(ProviderProfile $profile, int $id): void
    {
        $profile->areas()->whereKey($id)->delete() > 0 ?: throw ValidationException::withMessages(['area' => __('providers.onboarding.areas.errors.missing')]);
    }

    /** @return AreaRow */
    private function row(ProviderProfile $profile, ProviderServiceArea $area): array
    {
        $kind = match ($area->kind) {
            'city' => 'city',
            'zip' => 'zip',
            default => 'radius',
        };

        return [
            'id' => $area->id, 'kind' => $kind, 'city' => $area->city, 'postal_code' => $area->postal_code, 'radius_miles' => $area->radius_miles,
            'agents' => $this->agents($area), 'providers' => $this->providers($profile, $area),
        ];
    }

    /** Agents listing on the MLS in the area in the last 12 months. */
    private function agents(ProviderServiceArea $area): int
    {
        $listings = DB::table('mls_listings')->whereNotNull('list_agent_key')
            ->where(static fn (Builder $q) => $q->where('listed_on', '>=', now()->subYear()->toDateString())->orWhere('close_date', '>=', now()->subYear()->toDateString())->orWhere('status', 'active'));

        if ($area->kind === 'city') {
            $listings->whereRaw('lower(city) = ?', [mb_strtolower((string) $area->city)]);
        } elseif ($area->kind === 'zip') {
            $listings->where('postal_code', 'like', $area->postal_code.'%');
        } else {
            $centre = self::centre($area);

            if ($centre === null) {
                return 0;
            }

            [$lat, $lng] = $centre;

            // A box around the ZIP code's centre narrows the listings; the distance in miles decides.
            $miles = (int) $area->radius_miles;
            $dLat = $miles / 69.0;
            $dLng = $miles / max(1.0, 69.0 * cos(deg2rad($lat)));
            $agents = [];

            foreach ($listings->whereBetween('latitude', [$lat - $dLat, $lat + $dLat])->whereBetween('longitude', [$lng - $dLng, $lng + $dLng])->limit(20000)->get(['list_agent_key', 'latitude', 'longitude']) as $row) {
                if (is_numeric($row->latitude ?? null) && is_numeric($row->longitude ?? null) && self::miles($lat, $lng, (float) $row->latitude, (float) $row->longitude) <= $miles) {
                    $agents[(string) $row->list_agent_key] = true;
                }
            }

            return count($agents);
        }

        return (int) $listings->distinct()->count('list_agent_key');
    }

    /**
     * The middle of a radius area's ZIP code, from the MLS listings in it.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function centre(ProviderServiceArea $area): ?array
    {
        $centre = DB::table('mls_listings')->where('postal_code', 'like', $area->postal_code.'%')->whereNotNull('latitude')
            ->selectRaw('avg(latitude) as lat, avg(longitude) as lng')->first();
        $lat = is_object($centre) && is_numeric($centre->lat ?? null) ? (float) $centre->lat : null;
        $lng = is_object($centre) && is_numeric($centre->lng ?? null) ? (float) $centre->lng : null;

        return $lat === null || $lng === null ? null : [$lat, $lng];
    }

    /** Miles between two points (haversine). */
    public static function miles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 3958.8 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Live providers in the same category with the same city or ZIP code. */
    private function providers(ProviderProfile $profile, ProviderServiceArea $area): int
    {
        return ProviderProfile::query()->where('status', ProviderProfile::LIVE)->where('provider_category_id', $profile->provider_category_id)->whereKeyNot($profile->id)
            ->whereHas('areas', static fn ($q) => $area->kind === 'city' ? $q->whereRaw('lower(city) = ?', [mb_strtolower((string) $area->city)]) : $q->where('postal_code', $area->postal_code))
            ->count();
    }
}
