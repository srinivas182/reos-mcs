<?php

declare(strict_types=1);

namespace App\Mls;

/** The `mls` block of an integrations register entry (B10.1). */
final readonly class MlsDefinition
{
    public function __construct(
        public MlsVendor $vendor,
        /** Bridge dataset, Trestle feed or MLS Grid OriginatingSystemName. */
        public string $dataset,
        /** The MLS's name beside its listings ("HAR"). */
        public string $shortName,
        public MlsDisplayRules $display,
    ) {}
}
