<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

/** A question about a split sheet (A17.3), sent to the team leader. */
final class SplitQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:1000']];
    }
}
