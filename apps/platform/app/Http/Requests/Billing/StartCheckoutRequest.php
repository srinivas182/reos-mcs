<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting checkout (B5.1, B5.2a). PayPal and Authorize.net need the
 * buyer's ZIP code first, so REOS can work out the sales tax they charge;
 * Stripe asks for the address in its own fields.
 */
final class StartCheckoutRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** Five-digit US ZIP code, for the sales tax at gateways without their own. */
            'postal_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{5}$/'],
        ];
    }

    public function postalCode(): ?string
    {
        $code = $this->validated('postal_code');

        return is_string($code) ? $code : null;
    }
}
