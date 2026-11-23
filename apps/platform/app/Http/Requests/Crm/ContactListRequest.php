<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Crm\Segments;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** The CRM hub's list (B8.3): a tab, a tag or a search. */
final class ContactListRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            /** All, new, active, past or sphere. */
            'segment' => ['nullable', Rule::in(Segments::names())],
            /** One of the agent's tags. */
            'tag' => ['nullable', 'string', 'max:60'],
            /** Name, email or phone. */
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function segmentName(): string
    {
        return $this->string('segment')->toString() ?: Segments::ALL;
    }

    public function tag(): ?string
    {
        return $this->filled('tag') ? $this->string('tag')->toString() : null;
    }

    public function search(): ?string
    {
        return $this->filled('q') ? $this->string('q')->toString() : null;
    }
}
