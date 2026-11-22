<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Billing\ProductKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/** How one payment gateway is used (screen 66, B5.2a). Empty lists mean "all". */
final class GatewayRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Exists|In|string>> */
    public function rules(): array
    {
        return [
            /** State codes it serves; empty: every state. */
            'states' => ['present', 'array', 'max:60'],
            'states.*' => ['string', 'size:2', Rule::exists('states', 'code')],
            /** Kinds of product it's used for; empty: every kind. */
            'used_for' => ['present', 'array'],
            'used_for.*' => ['string', Rule::in(ProductKind::values())],
            'is_default' => ['required', 'boolean'],
            'tenant_accounts' => ['required', 'boolean'],
        ];
    }

    /** @return array{states: list<string>|null, used_for: list<string>|null, is_default: bool, tenant_accounts: bool} */
    public function settings(): array
    {
        return [
            'states' => self::list($this->validated('states')),
            'used_for' => self::list($this->validated('used_for')),
            'is_default' => $this->boolean('is_default'),
            'tenant_accounts' => $this->boolean('tenant_accounts'),
        ];
    }

    /** @return list<string>|null */
    private static function list(mixed $values): ?array
    {
        $list = is_array($values) ? array_values(array_filter($values, is_string(...))) : [];

        return $list === [] ? null : $list;
    }
}
