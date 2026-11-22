<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Supervising duties that change a licence's CE (A5.2): in Texas, Broker
 * Responsibility is required for brokers who sponsor agents, designated
 * brokers of sponsoring entities and delegated supervisors (22 TAC §535.92(a)(4)).
 */
enum BrokerRole: string
{
    case SponsoringBroker = 'sponsoring_broker';
    case DesignatedBroker = 'designated_broker';
    case DelegatedSupervisor = 'delegated_supervisor';

    /** Sponsoring and designated brokers must hold a broker licence; a delegated supervisor may hold either. */
    public function allows(LicenceType $type): bool
    {
        return $this === self::DelegatedSupervisor || $type === LicenceType::Broker;
    }
}
