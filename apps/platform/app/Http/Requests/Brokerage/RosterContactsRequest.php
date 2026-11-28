<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** Emails or phones for sponsored agents not on REOS: one line each, the TREC licence number then the contact (A15.1b). */
final class RosterContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['lines' => ['required', 'string', 'max:200000']];
    }
}
