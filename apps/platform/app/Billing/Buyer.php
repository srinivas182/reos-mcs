<?php

declare(strict_types=1);

namespace App\Billing;

use App\Authorization\Role;

/** Who a product is for (B5.1; screen 67's tabs). */
enum Buyer: string
{
    case Candidate = 'candidate';
    case Agent = 'agent';
    case Broker = 'broker';
    case School = 'school';
    case Provider = 'provider';
    case Association = 'association';

    /** The buyer a role shops as; admins of schools and associations buy for their organisation. */
    public static function forRole(?Role $role): ?self
    {
        return match ($role) {
            Role::Candidate => self::Candidate,
            Role::Agent => self::Agent,
            Role::Broker => self::Broker,
            Role::SchoolAdmin => self::School,
            Role::Provider => self::Provider,
            Role::AssociationAdmin => self::Association,
            default => null,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $buyer): string => $buyer->value, self::cases());
    }
}
