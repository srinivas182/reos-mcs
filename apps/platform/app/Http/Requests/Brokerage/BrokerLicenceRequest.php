<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use App\Brokerage\BrokerVerifications;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A broker licence to check against TREC, and, to confirm, the brokerage and the person's role (A15.1a). */
final class BrokerLicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $confirming = $this->routeIs('*.confirm');

        return [
            'licence' => ['required', 'string', 'max:20', 'regex:/^[0-9 \-]{5,20}$/'],
            'brokerage' => [$confirming ? 'required' : 'nullable', 'string', 'max:20'],
            'role' => [$confirming ? 'required' : 'nullable', Rule::in(BrokerVerifications::ROLES)],
        ];
    }
}
