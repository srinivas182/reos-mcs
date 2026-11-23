<?php

declare(strict_types=1);

namespace App\Mls;

/** Where an MLS listing is about to be shown; the display rules depend on it (B10.2). */
enum ListingPurpose: string
{
    /** The agent's own screens (My listings). */
    case Agent = 'agent';
    /** A post the agent shares (B11 publishes it). */
    case Post = 'post';
    /** The agent's public page. */
    case Public = 'public';
    /** Comparables in an agent's CMA. */
    case Cma = 'cma';

    /** Seen by consumers, so the MLS's public-display rules apply. */
    public function isPublic(): bool
    {
        return $this === self::Post || $this === self::Public;
    }
}
