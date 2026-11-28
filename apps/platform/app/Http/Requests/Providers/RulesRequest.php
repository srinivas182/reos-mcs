<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** Accepting the network rules: the version the provider read (B15.2). */
final class RulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['version' => ['required', 'string', 'max:20'], 'accept' => ['required', 'accepted']];
    }
}
