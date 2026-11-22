<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Why a licence was not approved; the person sees it. */
final class RejectLicenceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:500']];
    }

    public function note(): string
    {
        return $this->string('note')->trim()->toString();
    }
}
