<?php

declare(strict_types=1);

namespace App\Integrations;

use JsonException;
use RuntimeException;

/**
 * The services REOS can connect to, read from the catalogue that
 * `pnpm integrations:render` generates from docs/integrations/register.yaml.
 *
 * The register is the only vendor list (see ADR 0005): nothing here is
 * hard-coded, and CI fails if the generated file is stale.
 */
final class Catalogue
{
    /** @var array<string, IntegrationDefinition>|null */
    private ?array $definitions = null;

    public function __construct(private readonly string $path) {}

    /** @return list<IntegrationDefinition> */
    public function all(): array
    {
        return array_values($this->load());
    }

    public function find(string $id): ?IntegrationDefinition
    {
        return $this->load()[$id] ?? null;
    }

    /** @return array<string, IntegrationDefinition> */
    private function load(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException("Integration catalogue not found at {$this->path}. Run `pnpm integrations:render`.");
        }

        try {
            $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The integration catalogue is not valid JSON.', 0, $exception);
        }

        $entries = is_array($data) && is_array($data['entries'] ?? null) ? $data['entries'] : [];
        $definitions = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $definition = $this->definition($entry);
            $definitions[$definition->id] = $definition;
        }

        return $this->definitions = $definitions;
    }

    /** @param  array<mixed>  $entry */
    private function definition(array $entry): IntegrationDefinition
    {
        $connection = is_array($entry['connection'] ?? null) ? $entry['connection'] : [];
        $fields = is_array($connection['fields'] ?? null) ? $connection['fields'] : [];

        return new IntegrationDefinition(
            id: $this->string($entry, 'id'),
            name: $this->string($entry, 'name'),
            kind: $this->string($entry, 'kind'),
            category: $this->string($entry, 'category'),
            purpose: $this->string($entry, 'purpose'),
            launch: $this->string($entry, 'launch'),
            buildSprint: is_int($entry['build_sprint'] ?? null) ? $entry['build_sprint'] : 0,
            owner: $this->string($entry, 'owner'),
            accessStatus: $this->string($entry, 'access_status'),
            personalData: ($entry['personal_data'] ?? false) === true,
            sandbox: $this->string($entry, 'sandbox'),
            auth: AuthType::from($this->string($connection, 'auth')),
            accounts: AccountScope::from($this->string($connection, 'accounts')),
            fields: array_values(array_map(
                fn (mixed $field): ConnectionField => $this->field(is_array($field) ? $field : []),
                $fields,
            )),
        );
    }

    /** @param  array<mixed>  $field */
    private function field(array $field): ConnectionField
    {
        $default = $field['default'] ?? null;

        return new ConnectionField(
            key: $this->string($field, 'key'),
            secret: ($field['secret'] ?? false) === true,
            required: ($field['required'] ?? true) === true,
            kind: FieldKind::from($this->string($field, 'kind')),
            default: is_string($default) ? $default : null,
        );
    }

    /** @param  array<mixed>  $data */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new RuntimeException("The integration catalogue is missing \"{$key}\".");
        }

        return $value;
    }
}
