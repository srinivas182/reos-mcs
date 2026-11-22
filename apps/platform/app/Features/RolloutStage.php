<?php

declare(strict_types=1);

namespace App\Features;

/**
 * How far a flag has rolled out (A6.2, screen 70). Each step includes the
 * ones before it: named tenants also keeps REOS staff, states keeps the
 * named tenants, and so on.
 */
enum RolloutStage: string
{
    case Off = 'off';
    case Internal = 'internal';
    case Tenants = 'tenants';
    case States = 'states';
    case Percent = 'percent';
    case Everyone = 'everyone';

    /** Where the automatic rollback steps back to. */
    public function previous(): self
    {
        return match ($this) {
            self::Everyone => self::Percent,
            self::Percent => self::States,
            self::States => self::Tenants,
            self::Tenants => self::Internal,
            self::Internal, self::Off => self::Off,
        };
    }

    public function includes(self $step): bool
    {
        return $this->order() >= $step->order();
    }

    private function order(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
