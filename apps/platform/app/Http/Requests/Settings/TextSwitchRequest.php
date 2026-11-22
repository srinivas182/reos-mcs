<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/** Turning text alerts on or off (B6.2c). */
final class TextSwitchRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['on' => ['required', 'boolean']];
    }

    public function on(): bool
    {
        return $this->boolean('on');
    }
}
