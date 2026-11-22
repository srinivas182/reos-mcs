<?php

declare(strict_types=1);

namespace App\Http\Requests\Licences;

use App\Models\State;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Look a licence number up before adding it (agent journey screen 149). */
final class CheckLicenceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** The state's two-letter code, for example `TX`. */
            'state' => ['required', 'string', 'size:2', 'exists:states,code'],
            'number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]*$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('state'))) {
            $this->merge(['state' => strtoupper($this->input('state'))]);
        }
    }

    public function state(): State
    {
        return State::query()->findOrFail($this->string('state')->toString());
    }

    public function number(): string
    {
        return $this->string('number')->trim()->toString();
    }
}
