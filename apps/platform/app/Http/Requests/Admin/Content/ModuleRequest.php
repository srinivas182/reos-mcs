<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** A module's title (A5.3). */
final class ModuleRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'min:2', 'max:200']];
    }
}
