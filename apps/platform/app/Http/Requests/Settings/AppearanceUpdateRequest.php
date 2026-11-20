<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Appearance;
use App\Enums\TextSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class AppearanceUpdateRequest extends FormRequest
{
    /**
     * Either field may be sent on its own (each control saves as soon as it changes).
     *
     * @return array<string, array<int, ValidationRule|string|Enum>>
     */
    public function rules(): array
    {
        return [
            'appearance' => ['sometimes', 'required', Rule::enum(Appearance::class)],
            'text_size' => ['sometimes', 'required', Rule::enum(TextSize::class)],
        ];
    }
}
