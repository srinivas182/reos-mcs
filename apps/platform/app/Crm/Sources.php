<?php

declare(strict_types=1);

namespace App\Crm;

use App\Integrations\Catalogue;

/** A contact source's name as people know it: "Phone contacts", "Follow Up Boss" (B7.1). */
final readonly class Sources
{
    public function __construct(private Catalogue $catalogue) {}

    public function name(string $source): string
    {
        return match ($source) {
            Contacts::PHONE => __('crm.sources.phone'),
            Contacts::REOS => 'REOS',
            default => $this->catalogue->find($source)->name ?? $source,
        };
    }
}
