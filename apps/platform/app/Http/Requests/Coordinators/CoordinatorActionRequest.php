<?php

declare(strict_types=1);

namespace App\Http\Requests\Coordinators;

use Illuminate\Foundation\Http\FormRequest;

/** A coordinator action with nothing to choose (B17.1); the request exists for the route's contract. */
final class CoordinatorActionRequest extends FormRequest
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
