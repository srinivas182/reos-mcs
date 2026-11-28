<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** Remind all due agents (A16.2): nothing to choose; the request exists for the route's contract. */
final class RemindRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
