<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use App\Licences\ProviderLicences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** A provider's professional licence: its kind and number (B10.3). */
final class ProviderLicenceRequest extends FormRequest
{
    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(array_keys(ProviderLicences::KINDS))],
            'number' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9 -]+$/'],
        ];
    }

    /** @return 'trec_inspector'|'tdi'|'nmls' */
    public function kind(): string
    {
        return match ($this->string('kind')->toString()) {
            'trec_inspector' => 'trec_inspector',
            'tdi' => 'tdi',
            default => 'nmls',
        };
    }
}
