<?php

declare(strict_types=1);

namespace App\Mls;

/**
 * An MLS's display rules from its data licence (B10.2, decision 7), held in
 * the register and enforced by {@see ListingService} so no screen can break
 * them.
 */
final readonly class MlsDisplayRules
{
    public function __construct(
        /** Most photos any screen or post may show. */
        public int $maxPhotos,
        /** Listings older than this (since the last sync) aren't shown to the public. */
        public int $refreshHours,
        /** Sold prices may appear on public pages and posts. */
        public bool $soldPublic,
        /** Sold listings may be used in agents' CMAs. */
        public bool $soldInCma,
        /** Posts may only feature the agent's own listings. */
        public bool $ownListingsOnly,
        public bool $nameBrokerage,
        public bool $nameMls,
        public bool $showLogo,
        public ?string $logoUrl,
    ) {}

    /** @param  array<mixed>  $display */
    public static function from(array $display): self
    {
        $attribution = is_array($display['attribution'] ?? null) ? $display['attribution'] : [];
        $int = static fn (string $key, int $default): int => is_int($display[$key] ?? null) ? $display[$key] : $default;
        $logo = $display['logo_url'] ?? null;

        // Missing rules fall to the strictest reading.
        return new self(
            maxPhotos: $int('max_photos', 1),
            refreshHours: $int('refresh_hours', 12),
            soldPublic: ($display['sold_public'] ?? false) === true,
            soldInCma: ($display['sold_in_cma'] ?? false) === true,
            ownListingsOnly: ($display['own_listings_only'] ?? true) !== false,
            nameBrokerage: ($attribution['brokerage'] ?? true) !== false,
            nameMls: ($attribution['mls_name'] ?? true) !== false,
            showLogo: ($attribution['logo'] ?? false) === true,
            logoUrl: is_string($logo) ? $logo : null,
        );
    }
}
