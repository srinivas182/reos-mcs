<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

/** A started checkout: the gateway's session id and what the page needs to show its payment fields. */
final readonly class CheckoutSession
{
    public function __construct(public string $id, public ?string $clientSecret = null) {}
}
