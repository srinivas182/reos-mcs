<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Billing\Payouts\PayeeRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/** Instructors' and mentors' shares of a school product (screen 67, B5.2b). */
final class SplitsRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Exists|In|string>> */
    public function rules(): array
    {
        return [
            'splits' => ['present', 'array', 'max:10'],
            /** The instructor's or mentor's REOS email. */
            'splits.*.email' => ['required', 'email', 'distinct:ignore_case', Rule::exists('users', 'email')],
            'splits.*.role' => ['required', Rule::in(PayeeRole::splitValues())],
            /** Percent of what's left after the platform fee. */
            'splits.*.percent' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return list<array{user_id: int, role: string, percent: int}> */
    public function splits(): array
    {
        $rows = $this->validated('splits');
        $splits = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row) || ! is_string($row['email'] ?? null) || ! is_string($row['role'] ?? null) || ! is_numeric($row['percent'] ?? null)) {
                continue;
            }

            $id = User::query()->where('email', $row['email'])->value('id');

            if (is_int($id)) {
                $splits[] = ['user_id' => $id, 'role' => $row['role'], 'percent' => (int) $row['percent']];
            }
        }

        return $splits;
    }
}
