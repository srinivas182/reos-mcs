<?php

declare(strict_types=1);

namespace App\Connections;

/**
 * One page from a tool: records to save, ids the tool deleted, and the cursor
 * to carry on from. `done` means the stream is up to date; the cursor is then
 * the one to start the next incremental sync from.
 */
final readonly class SyncPage
{
    /**
     * @param  list<array<string, mixed>>  $records  Normalised records, each with an `external_id`.
     * @param  list<string>  $removed  External ids the tool deleted or cancelled.
     */
    public function __construct(
        public array $records,
        public array $removed,
        public ?string $cursor,
        public bool $done,
    ) {}
}
