<?php

declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

/** A question about a reviewed contract (A12.2; screen 66). */
final class ContractQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['question' => ['required', 'string', 'min:3', 'max:500']];
    }
}
