<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Onboarding\States;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Ticks one launch checklist item on screen 61. */
final class StateChecklistRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'item' => ['required', Rule::in(States::CHECKLIST)],
            'done' => ['required', 'boolean'],
        ];
    }
}
