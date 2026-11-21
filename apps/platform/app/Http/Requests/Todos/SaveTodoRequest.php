<?php

declare(strict_types=1);

namespace App\Http\Requests\Todos;

use App\Models\Todo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * A to-do from the confirm card or typed in (B4.3). Due at a moment
 * (`due_at`, with offset) or on a day (`due_date`), not both.
 */
final class SaveTodoRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            /** The phone's own id for this to-do (a UUID), so sending it twice saves it once. */
            'client_id' => ['sometimes', 'nullable', 'uuid'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            /** Due at this moment, with its offset. */
            'due_at' => ['sometimes', 'nullable', 'date', 'prohibits:due_date'],
            /** Due on this day, `YYYY-MM-DD`. */
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            /** When the phone reminds; at or before nothing in particular, but not in the far past. */
            'remind_at' => ['sometimes', 'nullable', 'date', 'after:-1 day'],
            /** The person it's about: `phone_contact` (the phone's own contact id) for now. */
            'related_type' => ['sometimes', 'nullable', Rule::in(['phone_contact']), 'required_with:related_id'],
            'related_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            /** Their name, as shown on the to-do. */
            'related_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            /** `typed` or `voice`. */
            'source' => ['sometimes', Rule::in(Todo::SOURCES)],
        ];
    }

    public function clientId(): ?string
    {
        $id = $this->input('client_id');

        return is_string($id) && $id !== '' ? mb_strtolower($id) : null;
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        $values = [];

        foreach (['title', 'notes', 'related_type', 'related_id', 'related_label', 'source'] as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $values[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }
        }

        foreach (['due_at', 'remind_at'] as $field) {
            if ($this->has($field)) {
                $values[$field] = $this->filled($field) ? CarbonImmutable::parse($this->string($field)->toString())->utc() : null;
            }
        }

        if ($this->has('due_date')) {
            $values['due_date'] = $this->filled('due_date') ? $this->string('due_date')->toString() : null;
        }

        if (array_key_exists('source', $values) && $values['source'] === null) {
            unset($values['source']);
        }

        return $values;
    }
}
