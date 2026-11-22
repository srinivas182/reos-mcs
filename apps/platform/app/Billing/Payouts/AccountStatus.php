<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

/** What Stripe says about a connected account (B5.2b). */
final readonly class AccountStatus
{
    /** @param  list<string>  $requirements  fields Stripe still needs */
    public function __construct(
        public bool $detailsSubmitted,
        public bool $payoutsEnabled,
        public array $requirements = [],
    ) {}
}
