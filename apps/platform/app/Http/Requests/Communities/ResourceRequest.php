<?php

declare(strict_types=1);

namespace App\Http\Requests\Communities;

use Illuminate\Foundation\Http\FormRequest;

/** A resource link for the community (B17.2). */
final class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'min:2', 'max:120'], 'url' => ['required', 'url:https', 'max:500']];
    }
}
