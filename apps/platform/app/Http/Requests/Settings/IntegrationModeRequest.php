<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use RuntimeException;

/** Switches how an integration runs; test and live need their keys first. */
final class IntegrationModeRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|string>> */
    public function rules(): array
    {
        return ['mode' => ['required', new Enum(IntegrationMode::class)]];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(IntegrationHub $hub): array
    {
        return [
            function (Validator $validator) use ($hub): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $reason = $hub->modeAvailability($this->definition())[$this->mode()->value];

                if ($reason !== null) {
                    $message = __($reason);
                    $validator->errors()->add('mode', is_string($message) ? $message : $reason);
                }
            },
        ];
    }

    public function mode(): IntegrationMode
    {
        return IntegrationMode::from($this->string('mode')->toString());
    }

    public function definition(): IntegrationDefinition
    {
        $definition = $this->route('integration');

        return $definition instanceof IntegrationDefinition
            ? $definition
            : throw new RuntimeException('Integration route value missing.');
    }
}
