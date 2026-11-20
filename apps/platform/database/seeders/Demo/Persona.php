<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * One demo person from the REOS journey documents.
 *
 * Sprint 2 attaches `role` and `organisation` to real roles and tenants;
 * until then they document who each demo login is.
 */
final readonly class Persona
{
    public function __construct(
        public string $role,
        public string $name,
        public string $email,
        public string $organisation,
        public string $title,
    ) {}
}
