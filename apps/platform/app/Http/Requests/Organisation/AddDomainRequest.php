<?php

declare(strict_types=1);

namespace App\Http\Requests\Organisation;

use App\Services\Tenancy\TenantDomains;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Your app address" (screens 41 and 57): a subdomain such as
 * learn.lonestarrealty.com. A bare domain can't point at REOS with a CNAME,
 * so at least three parts are needed.
 */
final class AddDomainRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['hostname' => TenantDomains::normalise($this->string('hostname')->toString())]);
    }

    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.){2,}[a-z]{2,63}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['hostname.regex' => __('domains.errors.format')];
    }

    public function hostname(): string
    {
        return $this->string('hostname')->toString();
    }
}
