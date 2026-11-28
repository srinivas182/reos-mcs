<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinators;

use Illuminate\Foundation\Http\FormRequest;

/** A message about a shared file to the agent (B17.1). */
final class FileMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:2000']];
    }
}
