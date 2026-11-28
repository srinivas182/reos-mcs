<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinators;

use Illuminate\Foundation\Http\FormRequest;

/** Tick or untick a checklist item on a shared file (B17.1): `doc:<name>` or a date's id. */
final class TickRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['item' => ['required', 'string', 'max:200'], 'done' => ['required', 'boolean']];
    }
}
