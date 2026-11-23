<?php

declare(strict_types=1);

namespace App\Connections\Crm;

/**
 * A CRM whose stage lives in a field the brokerage chooses (Salesforce,
 * B8.2): the fields screen 40 can map a stage to.
 */
interface MapsStageFields
{
    /** @return array{Contact: list<array{name: string, label: string}>, Lead: list<array{name: string, label: string}>} */
    public function stageFields(string $token): array;
}
