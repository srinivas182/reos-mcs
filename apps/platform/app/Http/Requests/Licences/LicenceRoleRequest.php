<?php

declare(strict_types=1);

namespace App\Http\Requests\Licences;

use App\Enums\BrokerRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Who a licence holder supervises (A5.1); changes their CE (A5.2). */
final class LicenceRoleRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|string>> */
    public function rules(): array
    {
        return [
            /** `sponsoring_broker`, `designated_broker`, `delegated_supervisor`, or null for none. */
            'broker_role' => ['present', 'nullable', Rule::enum(BrokerRole::class)],
        ];
    }

    public function role(): ?BrokerRole
    {
        return $this->filled('broker_role') ? BrokerRole::from($this->string('broker_role')->toString()) : null;
    }
}
