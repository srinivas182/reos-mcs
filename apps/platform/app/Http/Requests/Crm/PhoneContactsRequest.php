<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

/** A batch of the phone's contacts, read on the phone with permission (B7.1). */
final class PhoneContactsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contacts' => ['required', 'array', 'max:500'],
            'contacts.*.id' => ['required', 'string', 'max:191'],
            'contacts.*.first_name' => ['nullable', 'string', 'max:100'],
            'contacts.*.last_name' => ['nullable', 'string', 'max:100'],
            'contacts.*.emails' => ['nullable', 'array', 'max:10'],
            'contacts.*.emails.*' => ['string', 'max:191'],
            'contacts.*.phones' => ['nullable', 'array', 'max:10'],
            'contacts.*.phones.*' => ['string', 'max:40'],
            'contacts.*.birthday' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return list<array{external_id: string, first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, birthday: string|null}> */
    public function contacts(): array
    {
        $list = [];
        $strings = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, is_string(...))) : [];
        $text = static fn (mixed $v): ?string => is_string($v) ? $v : null;

        foreach ((array) $this->validated('contacts') as $row) {
            if (is_array($row) && is_string($row['id'] ?? null)) {
                $list[] = [
                    'external_id' => $row['id'], 'first_name' => $text($row['first_name'] ?? null), 'last_name' => $text($row['last_name'] ?? null),
                    'emails' => $strings($row['emails'] ?? null), 'phones' => $strings($row['phones'] ?? null), 'birthday' => $text($row['birthday'] ?? null),
                ];
            }
        }

        return $list;
    }
}
