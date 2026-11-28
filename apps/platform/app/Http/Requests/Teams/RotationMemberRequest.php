<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An agent in the rotation (A17.3): on or off, and the languages they take. */
final class RotationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['active' => ['required', 'boolean'], 'languages' => ['present', 'array', 'max:5'], 'languages.*' => ['string', Rule::in(['en', 'es'])]];
    }
}
