<?php

declare(strict_types=1);

namespace App\Mls\Reso;

/** One page of a RESO resource: raw RESO records and the link to the next page. */
final readonly class ResoPage
{
    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function __construct(
        public array $records,
        public ?string $next,
    ) {}
}
