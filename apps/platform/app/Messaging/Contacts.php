<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\User;

/** The people a person may start a conversation with, as the web and the app list them (B6.1). */
final readonly class Contacts
{
    public function __construct(private Relationships $relationships) {}

    /** @return list<array{id: int, name: string, role: string, organisation: string}> */
    public function for(User $user): array
    {
        return array_map(static fn (array $c): array => [
            'id' => $c['user']->id,
            'name' => $c['user']->name ?? $c['user']->email ?? '',
            'role' => $c['role']->value,
            'organisation' => $c['organisation'],
        ], $this->relationships->contacts($user));
    }
}
