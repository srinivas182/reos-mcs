<?php

declare(strict_types=1);

namespace App\Mls;

use App\Models\MlsFeed;
use App\Models\MlsListing;
use App\Practice\StudyPlans;
use Carbon\CarbonImmutable;

/**
 * The one way any feature reads MLS listings (B10.2, ADR 0042): My listings,
 * posts, CMAs, the agent's public page and open houses. Each MLS's display
 * rules from the register (decision 7) are applied here, so no screen can
 * break them:
 *
 * - **Photos:** never more than the MLS allows.
 * - **Attribution:** the listing brokerage and the MLS, wherever a listing shows.
 * - **Freshness:** public pages and posts show nothing older than the MLS's refresh age.
 * - **Sold data:** sold prices only where the MLS allows them (Texas is a
 *   non-disclosure state, so not on public pages or posts by default).
 * - **Consumer display:** only listings the MLS lets the public see
 *   (`InternetEntireListingDisplayYN`), with the address withheld where it asks.
 * - **Posts:** the agent's own listings only, where the MLS says so.
 *
 * @phpstan-type ListingView array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, address: string, city: string|null, postal_code: string|null, price: int|null, close_price: int|null, close_date: string|null, beds: int|null, baths: float|null, area: int|null, price_per_sqft: int|null, photo: array{url: string, caption: string|null}|null, photos: list<array{url: string, caption: string|null}>, listed_on: string|null, days_on_market: int|null, office: string|null, latitude: float|null, longitude: float|null, attribution: string, logo_url: string|null, sample: bool}
 */
final class ListingService
{
    /** Posts carry at most this many photos, within the MLS's own limit. */
    public const POST_PHOTOS = 10;

    /** @var array<string, bool> integration => fresh enough for the public */
    private array $fresh = [];

    public function __construct(private readonly MlsFeeds $feeds) {}

    public function rules(string $integration): MlsDisplayRules
    {
        return $this->feeds->find($integration)?->mls->display ?? MlsDisplayRules::from([]);
    }

    /**
     * Whether this listing may be shown for this purpose at all.
     *
     * @param  bool  $own  the agent is its list or co-list agent
     */
    public function allows(MlsListing $listing, ListingPurpose $purpose, bool $own = false): bool
    {
        $rules = $this->rules($listing->integration);

        if ($purpose->isPublic()) {
            if (! $listing->idx || ! $this->isFresh($listing->integration) || ($listing->status === 'closed' && ! $rules->soldPublic)) {
                return false;
            }

            return $purpose !== ListingPurpose::Post || $own || ! $rules->ownListingsOnly;
        }

        return $purpose !== ListingPurpose::Cma || $listing->status !== 'closed' || $rules->soldInCma;
    }

    /**
     * The listing as this purpose may show it, or null when it may not.
     *
     * @return ListingView|null
     */
    public function view(MlsListing $listing, ListingPurpose $purpose, bool $own = false): ?array
    {
        if (! $this->allows($listing, $purpose, $own)) {
            return null;
        }

        $rules = $this->rules($listing->integration);
        $definition = $this->feeds->find($listing->integration);
        $shortName = $definition?->mls->shortName ?? $listing->integration;
        $limit = $purpose === ListingPurpose::Post ? min(self::POST_PHOTOS, $rules->maxPhotos) : $rules->maxPhotos;
        $photos = array_slice($listing->photos, 0, $limit);
        $sold = $listing->status === 'closed' && match ($purpose) {
            ListingPurpose::Agent => true,
            ListingPurpose::Cma => $rules->soldInCma,
            default => $rules->soldPublic,
        };
        $hideAddress = $purpose->isPublic() && ! $listing->address_shown;
        $unit = $listing->unit !== null && ! str_contains((string) $listing->street, $listing->unit) ? ' #'.$listing->unit : '';
        $today = CarbonImmutable::now(StudyPlans::TIMEZONE)->startOfDay();
        $end = $listing->status === 'closed' && $listing->close_date !== null ? $listing->close_date : $today;
        $price = $sold ? ($listing->close_price ?? $listing->list_price) : $listing->list_price;

        return [
            'uuid' => $listing->uuid,
            'mls' => $listing->integration,
            'short_name' => $shortName,
            'listing_id' => $listing->listing_id,
            'status' => $listing->status,
            'address' => $hideAddress || $listing->street === null ? __('listings.addressWithheld', ['city' => $listing->city ?? '']) : $listing->street.$unit,
            'city' => $listing->city,
            'postal_code' => $listing->postal_code,
            'price' => $listing->status === 'closed' && ! $sold ? null : $listing->list_price,
            'close_price' => $sold ? $listing->close_price : null,
            'close_date' => $sold ? $listing->close_date?->toDateString() : null,
            'beds' => $listing->bedrooms,
            'baths' => $listing->bathrooms === null ? null : (float) $listing->bathrooms,
            'area' => $listing->living_area,
            'price_per_sqft' => $price !== null && ($listing->status !== 'closed' || $sold) && ($listing->living_area ?? 0) > 0 ? (int) round($price / $listing->living_area) : null,
            'photo' => $photos[0] ?? null,
            'photos' => $photos,
            'listed_on' => $listing->listed_on?->toDateString(),
            'days_on_market' => $listing->listed_on === null ? null : max(0, (int) $listing->listed_on->diffInDays($end)),
            'office' => $listing->list_office_name,
            'latitude' => $hideAddress || $listing->latitude === null ? null : (float) $listing->latitude,
            'longitude' => $hideAddress || $listing->longitude === null ? null : (float) $listing->longitude,
            'attribution' => $this->attribution($listing, $rules, $shortName),
            'logo_url' => $rules->showLogo ? $rules->logoUrl : null,
            'sample' => $this->feeds->isSample($listing->integration),
        ];
    }

    /** "Listing courtesy of Lone Star Realty. Source: HAR MLS." as the MLS requires. */
    public function attribution(MlsListing $listing, MlsDisplayRules $rules, string $shortName): string
    {
        $parts = [];

        if ($rules->nameBrokerage && $listing->list_office_name !== null) {
            $parts[] = __('listings.attribution.brokerage', ['office' => $listing->list_office_name]);
        }

        if ($rules->nameMls) {
            $parts[] = __('listings.attribution.mls', ['mls' => $shortName]);
        }

        return implode(' ', $parts);
    }

    /** The MLS feed synced within its refresh age (public pages and posts need it). */
    public function isFresh(string $integration): bool
    {
        if (isset($this->fresh[$integration])) {
            return $this->fresh[$integration];
        }

        $synced = MlsFeed::query()->where('integration', $integration)->first()?->synced_at;

        return $this->fresh[$integration] = $synced !== null && $synced->gt(now()->subHours($this->rules($integration)->refreshHours));
    }
}
