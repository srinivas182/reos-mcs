<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * One demo person from the REOS journey documents.
 *
 * The seeder grants `role` (and any `alsoRoles`) in `organisation`.
 */
final readonly class Persona
{
    public function __construct(
        public string $role,
        public string $name,
        public string $email,
        public string $organisation,
        public string $title,
        /** @var list<string> More roles held in the same organisation. */
        public array $alsoRoles = [],
    ) {}

    /** @return list<string> */
    public function roles(): array
    {
        return [$this->role, ...$this->alsoRoles];
    }
}
