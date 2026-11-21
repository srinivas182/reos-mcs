<?php

declare(strict_types=1);

namespace App\Connections;

use App\Models\Connection;

/** Saves one stream's records. Upserts are idempotent: the same page twice changes nothing. */
interface StreamStore
{
    /**
     * @param  list<array<string, mixed>>  $records
     * @return int Records written.
     */
    public function upsert(Connection $connection, array $records): int;

    /**
     * @param  list<string>  $externalIds
     * @return int Records removed.
     */
    public function remove(Connection $connection, array $externalIds): int;

    /** Everything this connection synced (on disconnect). */
    public function forget(Connection $connection): void;
}
