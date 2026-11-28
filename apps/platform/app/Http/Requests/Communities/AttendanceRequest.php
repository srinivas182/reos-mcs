<?php

declare(strict_types=1);

namespace App\Http\Requests\Communities;

use Illuminate\Foundation\Http\FormRequest;

/** Who came to a session (B17.2). */
final class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['user_ids' => ['present', 'array', 'max:2000'], 'user_ids.*' => ['integer']];
    }

    /** @return list<int> */
    public function ids(): array
    {
        $out = [];

        foreach ((array) $this->input('user_ids', []) as $id) {
            if (is_numeric($id)) {
                $out[] = (int) $id;
            }
        }

        return $out;
    }
}
