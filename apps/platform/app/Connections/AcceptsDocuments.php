<?php

declare(strict_types=1);

namespace App\Connections;

use App\Models\Connection;
use App\Models\Deal;

/**
 * A transaction tool that takes documents on its transactions (B9.3,
 * ADR 0038): the signed copy is filed there as well as on the deal.
 */
interface AcceptsDocuments
{
    /** Adds the PDF to the deal's transaction in the tool. False when the tool has nowhere to put it. */
    public function uploadDocument(Connection $connection, string $accessToken, Deal $deal, string $name, string $contents): bool;
}
