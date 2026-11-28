<?php

declare(strict_types=1);

namespace App\Http\Requests\Communities;

use Illuminate\Foundation\Http\FormRequest;

/** A community action with nothing to choose (B17.2); the request exists for the route's contract. */
final class CommunityActionRequest extends FormRequest
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
