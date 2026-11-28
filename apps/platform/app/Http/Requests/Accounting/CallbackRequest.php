<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/** Intuit's redirect back after the broker connects the company (B17.3). */
final class CallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['state' => ['required', 'string', 'size:40'], 'code' => ['required', 'string', 'max:500'], 'realmId' => ['required', 'string', 'max:40', 'regex:/^\d+$/']];
    }
}
