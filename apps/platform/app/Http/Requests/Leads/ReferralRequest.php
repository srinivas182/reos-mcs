<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Leads\Referrals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A client to refer to another agent (B14.3b). The client must have
 * agreed to be referred; the fee is what the two brokers agreed.
 */
final class ReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'to_name' => ['required', 'string', 'min:2', 'max:120'],
            'to_email' => ['required', 'email', 'max:160'],
            'to_brokerage' => ['nullable', 'string', 'max:160'],
            'client_name' => ['required', 'string', 'min:2', 'max:120'],
            'client_email' => ['nullable', 'required_without:client_phone', 'email', 'max:160'],
            'client_phone' => ['nullable', 'required_without:client_email', 'string', 'max:30'],
            'kind' => ['required', Rule::in(Referrals::KINDS)],
            'area' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'fee_percent' => ['nullable', 'integer', 'min:0', 'max:50'],
            'client_agreed' => ['accepted'],
        ];
    }

    /** @return array{to_name: string, to_email: string, to_brokerage: string|null, client_name: string, client_email: string|null, client_phone: string|null, kind: 'buyer'|'seller', area: string|null, note: string|null, fee_percent: int|null} */
    public function referral(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->toString() : null;

        return [
            'to_name' => $this->string('to_name')->toString(), 'to_email' => $this->string('to_email')->toString(), 'to_brokerage' => $text('to_brokerage'),
            'client_name' => $this->string('client_name')->toString(), 'client_email' => $text('client_email'), 'client_phone' => $text('client_phone'),
            'kind' => $this->string('kind')->toString() === 'seller' ? 'seller' : 'buyer', 'area' => $text('area'), 'note' => $text('note'),
            'fee_percent' => $this->filled('fee_percent') ? $this->integer('fee_percent') : null,
        ];
    }
}
