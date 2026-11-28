<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Accounting\QuickBooksExports;
use Illuminate\Foundation\Http\FormRequest;

/** The QuickBooks account for each role (B17.3), mapped once. */
final class AccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = ['accounts' => ['required', 'array']];

        foreach (QuickBooksExports::ROLES as $role) {
            $rules["accounts.{$role}"] = ['required', 'string', 'max:40'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function accounts(): array
    {
        $out = [];

        foreach (QuickBooksExports::ROLES as $role) {
            $out[$role] = $this->string("accounts.{$role}")->toString();
        }

        return $out;
    }
}
