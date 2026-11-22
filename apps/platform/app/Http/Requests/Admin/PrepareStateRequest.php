<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** "Add state" on screen 61: start preparing a state, with its regulator and hours. */
final class PrepareStateRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'regulator' => ['required', 'string', 'max:120'],
            'exam_vendor' => ['nullable', 'string', 'max:120'],
            'qualifying_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'ce_hours' => ['nullable', 'integer', 'min:0', 'max:200'],
        ];
    }

    public function optionalInt(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }

    public function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
