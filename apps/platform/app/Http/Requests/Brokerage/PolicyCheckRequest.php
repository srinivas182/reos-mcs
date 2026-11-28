<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** Words to check against TREC's rules as a brokerage rule is typed (A15.2). */
final class PolicyCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['text' => ['required', 'string', 'min:5', 'max:4000']];
    }
}
