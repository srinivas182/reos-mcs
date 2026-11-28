<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** The plan to buy: basic (Listed), pro or premium (Partner) (B15.2). */
final class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['tier' => ['required', 'string', 'in:basic,pro,premium']];
    }
}
