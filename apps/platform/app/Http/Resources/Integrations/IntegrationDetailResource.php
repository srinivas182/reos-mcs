<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Integrations\AuthType;
use App\Integrations\ConnectionField;
use App\Integrations\CredentialEnvironment;
use App\Integrations\IntegrationDefinition;
use App\Integrations\IntegrationMode;
use App\Integrations\IntegrationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One integration's page: what it is, how it runs, its fields and which keys
 * are saved. Secret values never appear (only their last four characters).
 */
final class IntegrationDetailResource extends JsonResource
{
    /**
     * @param  array<value-of<IntegrationMode>, string|null>  $modes
     * @param  array<value-of<CredentialEnvironment>, array<string, array{saved: bool, value: string|null, last4: string|null}>>  $credentials
     */
    public function __construct(
        private readonly IntegrationDefinition $definition,
        private readonly IntegrationStatus $status,
        private readonly array $modes,
        private readonly array $credentials,
    ) {
        parent::__construct($definition);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new IntegrationSummaryResource($this->definition, $this->status))->toArray($request),
            'sandbox_notes' => $this->definition->sandbox,
            'build_sprint' => $this->definition->buildSprint,
            'auth' => $this->definition->auth->value,
            'accounts' => $this->definition->accounts->value,
            'has_test_system' => $this->definition->hasTestSystem(),
            // Where the vendor sends people back after they approve access.
            // Each integration's connect flow (its own sprint) handles this address.
            'redirect_url' => $this->definition->auth === AuthType::OAuth2
                ? url("integrations/{$this->definition->id}/callback")
                : null,
            'fields' => array_map(static fn (ConnectionField $field): array => [
                'key' => $field->key,
                'secret' => $field->secret,
                'required' => $field->required,
                'kind' => $field->kind->value,
                'default' => $field->default,
            ], $this->definition->fields),
            'credentials' => $this->credentials,
            'modes' => array_map(
                static fn (string $mode, ?string $reason): array => ['value' => $mode, 'available' => $reason === null, 'reason' => $reason],
                array_keys($this->modes),
                array_values($this->modes),
            ),
        ];
    }
}
