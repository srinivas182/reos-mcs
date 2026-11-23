<?php

declare(strict_types=1);

namespace App\Mls;

/**
 * Who delivers an MLS's RESO Web API feed (ADR 0042). Every vendor speaks
 * OData; the differences (sign-in, media, filters, deletions) live in
 * {@see Reso\ResoWebApi} so the rest of REOS never sees them.
 */
enum MlsVendor: string
{
    case Bridge = 'bridge';
    case Trestle = 'trestle';
    case MlsGrid = 'mls_grid';
    /** The MLS hasn't named its vendor yet: Simulator only. */
    case ToConfirm = 'to_confirm';

    /** Whether Property needs `$expand=Media` (Bridge returns photos inline). */
    public function expandsMedia(): bool
    {
        return $this === self::Trestle || $this === self::MlsGrid;
    }
}
