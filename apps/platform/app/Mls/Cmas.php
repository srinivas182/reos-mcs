<?php

declare(strict_types=1);

namespace App\Mls;

use App\Deals\DealAccess;
use App\Models\MlsListing;
use App\Models\MlsMemberLink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * A comparative market analysis for one of the agent's listings (B10.2b):
 * sold and active comparables from the same MLS within a radius and a date
 * range, each one's price per square foot applied to the subject's size
 * (the size adjustment), and a range from the sold ones. Read through the
 * listing service, so each MLS's rules (sold data in CMAs, photos,
 * attribution) hold here too.
 *
 * @phpstan-import-type ListingView from ListingService
 *
 * @phpstan-type Comparable array{listing: ListingView, distance: float, adjusted: int|null}
 * @phpstan-type Cma array{subject: ListingView, radius: float, months: int, comparables: list<Comparable>, sold: int, active: int, median_per_sqft: int|null, low: int|null, high: int|null, suggested: int|null, sample: bool}
 */
final readonly class Cmas
{
    public const RADII = [0.5, 1.0, 2.0, 5.0];

    public const MONTHS = [3, 6, 12];

    /** Most comparables in one CMA, nearest first. */
    public const LIMIT = 25;

    public function __construct(private ListingService $listings, private DealAccess $access) {}

    /** @return Cma */
    public function for(User $user, string $uuid, float $radius, int $months): array
    {
        $this->access->allow($user);
        $subject = MlsListing::query()->where('uuid', $uuid)->firstOrFail();
        $own = MlsMemberLink::query()->where('user_id', $user->id)->where('status', MlsMemberLink::CONFIRMED)->where('integration', $subject->integration)
            ->whereIn('member_key', array_filter([$subject->list_agent_key, $subject->co_list_agent_key]))->exists();
        $view = $own ? $this->listings->view($subject, ListingPurpose::Agent, true) : null;

        if ($view === null) {
            throw ValidationException::withMessages(['listing' => __('listings.post.errors.notYours')]);
        }

        if ($subject->latitude === null || $subject->longitude === null) {
            throw ValidationException::withMessages(['listing' => __('listings.cma.errors.noLocation')]);
        }

        $lat = (float) $subject->latitude;
        $lng = (float) $subject->longitude;
        // A box around the subject first (cheap, indexed columns), then the true distance.
        $dLat = $radius / 69.0;
        $dLng = $radius / max(1.0, 69.0 * cos(deg2rad($lat)));
        $since = CarbonImmutable::now()->subMonths($months)->toDateString();

        $candidates = MlsListing::query()
            ->where('integration', $subject->integration)->where('id', '!=', $subject->id)
            ->whereBetween('latitude', [$lat - $dLat, $lat + $dLat])->whereBetween('longitude', [$lng - $dLng, $lng + $dLng])
            ->when($subject->property_sub_type !== null, static fn ($q) => $q->where('property_sub_type', $subject->property_sub_type))
            ->where(static fn ($q) => $q->whereIn('status', ['active', 'under_contract', 'pending'])->orWhere(static fn ($c) => $c->where('status', 'closed')->where('close_date', '>=', $since)))
            ->limit(500)->get();

        $comparables = [];

        foreach ($candidates as $candidate) {
            $distance = self::miles($lat, $lng, (float) $candidate->latitude, (float) $candidate->longitude);
            $comp = $distance <= $radius ? $this->listings->view($candidate, ListingPurpose::Cma) : null;

            if ($comp === null) {
                continue;
            }

            $comparables[] = [
                'listing' => $comp,
                'distance' => round($distance, 2),
                'adjusted' => $comp['price_per_sqft'] !== null && $view['area'] !== null ? (int) (round($comp['price_per_sqft'] * $view['area'] / 1000) * 1000) : null,
            ];
        }

        usort($comparables, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);
        $comparables = array_slice($comparables, 0, self::LIMIT);
        $sold = array_values(array_filter($comparables, static fn (array $c): bool => $c['listing']['status'] === 'closed'));
        // The range comes from what sold; with fewer than three sales, from everything.
        $basis = count($sold) >= 3 ? $sold : $comparables;
        $adjusted = array_values(array_filter(array_column($basis, 'adjusted'), is_int(...)));
        $perSqft = array_values(array_filter(array_map(static fn (array $c): ?int => $c['listing']['price_per_sqft'], $basis), is_int(...)));

        return [
            'subject' => $view,
            'radius' => $radius,
            'months' => $months,
            'comparables' => $comparables,
            'sold' => count($sold),
            'active' => count($comparables) - count($sold),
            'median_per_sqft' => self::percentile($perSqft, 50),
            'low' => self::percentile($adjusted, 25),
            'high' => self::percentile($adjusted, 75),
            'suggested' => self::percentile($adjusted, 50),
            'sample' => $view['sample'],
        ];
    }

    /** Great-circle distance in miles. */
    public static function miles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 3958.8 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Linear-interpolated percentile, rounded to the nearest whole number.
     *
     * @param  list<int>  $values
     */
    public static function percentile(array $values, int $p): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $rank = ($p / 100) * (count($values) - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        $value = $values[$low] + ($values[$high] - $values[$low]) * ($rank - $low);

        return (int) round($value);
    }
}
