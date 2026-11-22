<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

/**
 * A started checkout: the gateway's session id and what the page needs:
 * Stripe's embedded fields (`clientSecret`), or the gateway's own hosted
 * page to send the buyer to (`redirect`: a link, or a form to post).
 */
final readonly class CheckoutSession
{
    /** @param  array{url: string, method: 'GET'|'POST', fields: array<string, string>}|null  $redirect */
    public function __construct(public string $id, public ?string $clientSecret = null, public ?array $redirect = null) {}
}
