<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Integrations\CredentialEnvironment;
use App\Integrations\FieldKind;
use App\Integrations\IntegrationDefinition;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use RuntimeException;

/**
 * Keys for one integration and environment. Only the fields the register
 * lists are accepted; a blank secret keeps the saved value.
 */
final class IntegrationCredentialsRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        $definition = $this->definition();
        $rules = ['values' => ['required', 'array:'.implode(',', $definition->fieldKeys())]];

        foreach ($definition->fields as $field) {
            $rules["values.{$field->key}"] = [
                'nullable',
                'string',
                $field->kind === FieldKind::Multiline ? 'max:16384' : 'max:2048',
                ...match ($field->kind) {
                    FieldKind::Url => ['url:https,http'],
                    FieldKind::Email => ['email'],
                    FieldKind::Phone => ['regex:/^\+?[0-9 ()\-]{7,20}$/'],
                    FieldKind::Text, FieldKind::Multiline => [],
                },
            ];
        }

        return $rules;
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(IntegrationHub $hub): array
    {
        return [
            function (Validator $validator) use ($hub): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $definition = $this->definition();
                $merged = $hub->merge($definition, $this->environment(), $this->values());

                foreach ($hub->missingRequired($definition, $merged) as $key) {
                    $validator->errors()->add("values.{$key}", __('validation.required', [
                        'attribute' => __("integrations.fields.{$key}"),
                    ]));
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        $values = $this->input('values');

        return is_array($values) ? array_filter($values, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }

    public function definition(): IntegrationDefinition
    {
        $definition = $this->route('integration');

        return $definition instanceof IntegrationDefinition
            ? $definition
            : throw new RuntimeException('Integration route value missing.');
    }

    public function environment(): CredentialEnvironment
    {
        $environment = $this->route('environment');

        return $environment instanceof CredentialEnvironment
            ? $environment
            : CredentialEnvironment::from(is_string($environment) ? $environment : '');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->definition()->fields as $field) {
            $label = __("integrations.fields.{$field->key}");
            $attributes["values.{$field->key}"] = is_string($label) ? $label : $field->key;
        }

        return $attributes;
    }
}
