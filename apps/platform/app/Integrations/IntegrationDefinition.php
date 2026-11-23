<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Licences\Regulators\RegulatorDefinition;
use App\Mls\MlsDefinition;

/**
 * One service from the integrations register, as the product sees it.
 *
 * Loaded from the generated catalogue (see {@see Catalogue}); edit
 * docs/integrations/register.yaml to change it.
 */
final readonly class IntegrationDefinition
{
    /**
     * @param  list<ConnectionField>  $fields
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $kind,
        public string $category,
        public string $purpose,
        public string $launch,
        public int $buildSprint,
        public string $owner,
        public string $accessStatus,
        public bool $personalData,
        public string $sandbox,
        public AuthType $auth,
        public AccountScope $accounts,
        public array $fields,
        public ?MlsDefinition $mls = null,
        public ?RegulatorDefinition $regulator = null,
    ) {}

    /** Whether the super admin enters any keys for this service. */
    public function needsKeys(): bool
    {
        return $this->fields !== [];
    }

    /** Public-data services have no vendor test system. */
    public function hasTestSystem(): bool
    {
        return $this->auth !== AuthType::None;
    }

    public function field(string $key): ?ConnectionField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function fieldKeys(): array
    {
        return array_map(static fn (ConnectionField $field): string => $field->key, $this->fields);
    }
}
