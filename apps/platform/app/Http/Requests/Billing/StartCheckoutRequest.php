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
            /** The brokerage or school sign-up this payment is for (A6.1b). */
            'signup' => ['sometimes', 'nullable', 'uuid'],
            /** The held 1-on-1 session booking this payment is for (A10.2a). */
            'booking' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function signup(): ?string
    {
        $signup = $this->validated('signup');

        return is_string($signup) ? $signup : null;
    }

    public function booking(): ?string
    {
        $booking = $this->validated('booking');

        return is_string($booking) ? $booking : null;
    }

    public function postalCode(): ?string
    {
        $code = $this->validated('postal_code');

        return is_string($code) ? $code : null;
    }
}
