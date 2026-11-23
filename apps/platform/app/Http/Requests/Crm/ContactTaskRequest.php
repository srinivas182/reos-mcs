<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** A task about a contact (B8.3): what, and on which day. */
final class ContactTaskRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            /** The day it's due, `YYYY-MM-DD`. */
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function title(): string
    {
        return trim($this->string('title')->toString());
    }

    public function due(): ?CarbonImmutable
    {
        return $this->filled('due_date') ? CarbonImmutable::createFromFormat('Y-m-d', $this->string('due_date')->toString())?->startOfDay() : null;
    }
}
