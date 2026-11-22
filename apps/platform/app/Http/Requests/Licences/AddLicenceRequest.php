<?php

declare(strict_types=1);

namespace App\Http\Requests\Licences;

use App\Enums\BrokerRole;
use App\Enums\LicenceType;
use App\Models\State;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** "Add a licence" (agent journey screen 149, web screen 23): a state and the licence number. */
final class AddLicenceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|string>> */
    public function rules(): array
    {
        return [
            /** The state's two-letter code, for example `TX`. */
            'state' => ['required', 'string', 'size:2', 'exists:states,code'],
            /** The licence number as the regulator shows it. */
            'number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]*$/'],
            /** Used only when the regulator can't be checked; the regulator's record wins. */
            'type' => ['sometimes', 'nullable', Rule::enum(LicenceType::class)],
            /** Who they supervise, if anyone (changes their CE). */
            'broker_role' => ['sometimes', 'nullable', Rule::enum(BrokerRole::class)],
            /**
             * Send it to a reviewer when the regulator has no such licence.
             *
             * @var bool
             */
            'review' => ['sometimes', 'boolean'],
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
        return State::query()->findOrFail(strtoupper($this->string('state')->toString()));
    }

    public function number(): string
    {
        return $this->string('number')->trim()->toString();
    }

    public function type(): ?LicenceType
    {
        return $this->filled('type') ? LicenceType::from($this->string('type')->toString()) : null;
    }

    public function role(): ?BrokerRole
    {
        return $this->filled('broker_role') ? BrokerRole::from($this->string('broker_role')->toString()) : null;
    }

    public function askReview(): bool
    {
        return $this->boolean('review');
    }
}
