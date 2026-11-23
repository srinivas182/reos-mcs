<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\OrganisationType;

/**
 * The cast of the REOS journey documents (candidate, agent, provider, broker,
 * instructor and web-app journeys), so demos and manual testing use the same
 * people the designs show. One login per role; Linda (broker) and Dana
 * (mentor) are also agents, to show the role switcher.
 */
final class Personas
{
    /** Shared e-mail domain. `.test` is reserved and can never receive real mail. */
    public const DOMAIN = 'demo.reos.test';

    /**
     * @return list<Persona>
     */
    public static function all(): array
    {
        return [
            new Persona('candidate', 'Sofia Reyes', self::email('sofia.reyes'), 'Texas Realty School', 'Pre-licensing candidate'),
            new Persona('agent', 'James Miller', self::email('james.miller'), 'Lone Star Realty', 'Licensed sales agent'),
            new Persona('broker', 'Linda Park', self::email('linda.park'), 'Lone Star Realty', 'Designated broker and team leader', ['agent']),
            new Persona('mentor', 'Dana Brooks', self::email('dana.brooks'), 'Lone Star Realty', 'Mentor', ['agent']),
            new Persona('instructor', 'Dr. Rachel Hughes', self::email('rachel.hughes'), 'Texas Realty School', 'Instructor'),
            new Persona('school_admin', 'Paul Diaz', self::email('paul.diaz'), 'Texas Realty School', 'School administrator'),
            new Persona('provider', 'Ana Ruiz', self::email('ana.ruiz'), 'Katy Home Loans', 'Service provider (mortgage)'),
            new Persona('association_admin', 'Karen Webb', self::email('karen.webb'), 'Texas REALTORS®', 'Association administrator'),
            new Persona('super_admin', 'REOS Operations', self::email('ops'), 'REOS platform', 'Platform super administrator'),
        ];
    }

    /**
     * The organisations the personas belong to, and their kind.
     *
     * @return array<string, OrganisationType>
     */
    public static function organisations(): array
    {
        return [
            'REOS platform' => OrganisationType::Platform,
            'Lone Star Realty' => OrganisationType::Brokerage,
            'Texas Realty School' => OrganisationType::School,
            'Katy Home Loans' => OrganisationType::ProviderBusiness,
            'Texas REALTORS®' => OrganisationType::Association,
        ];
    }

    public static function email(string $local): string
    {
        return $local.'@'.self::DOMAIN;
    }
}
