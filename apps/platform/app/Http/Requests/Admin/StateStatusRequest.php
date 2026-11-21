<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StateStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StateStatusRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|string>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(StateStatus::class)]];
    }

    public function status(): StateStatus
    {
        return StateStatus::from($this->string('status')->toString());
    }
}
