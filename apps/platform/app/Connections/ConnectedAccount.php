<?php

declare(strict_types=1);

namespace App\Connections;

/** The account at the tool: its stable id and a label people recognise (usually the email). */
final readonly class ConnectedAccount
{
    public function __construct(
        public string $externalId,
        public ?string $label,
    ) {}
}
