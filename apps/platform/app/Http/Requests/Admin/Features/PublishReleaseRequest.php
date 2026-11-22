<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Features;

use App\Authorization\Role;
use App\Features\FlagCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** New release (screen 70): version, date, a plain-words note per role and the flags it rolls out. */
final class PublishReleaseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'version' => ['required', 'string', 'max:32', 'regex:/^[0-9A-Za-z.\-]+$/', Rule::unique('releases', 'version')],
            'released_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['required', 'array'],
            'flags' => ['present', 'array'],
            'flags.*' => ['string', 'distinct', Rule::in(FlagCatalogue::keys())],
        ];

        foreach (Role::cases() as $role) {
            $rules["notes.{$role->value}"] = ['nullable', 'string', 'max:600'];
        }

        return $rules;
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->notes() === []) {
                $validator->errors()->add('notes', __('features.errors.noNotes'));
            }
        }];
    }

    /** @return array<string, string> role value => note */
    public function notes(): array
    {
        $notes = [];
        $given = $this->input('notes');

        foreach (Role::cases() as $role) {
            $note = is_array($given) ? ($given[$role->value] ?? null) : null;

            if (is_string($note) && trim($note) !== '') {
                $notes[$role->value] = trim($note);
            }
        }

        return $notes;
    }

    /** @return array{version: string, released_on: string, notes: array<string, string>, flags: list<string>} */
    public function release(): array
    {
        $flags = $this->input('flags');

        return [
            'version' => $this->string('version')->trim()->toString(),
            'released_on' => $this->string('released_on')->toString(),
            'notes' => $this->notes(),
            'flags' => is_array($flags) ? array_values(array_filter($flags, is_string(...))) : [],
        ];
    }
}
