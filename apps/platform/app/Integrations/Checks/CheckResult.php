<?php

declare(strict_types=1);

namespace App\Integrations\Checks;

/**
 * What a tester found. `message` is a translation key under
 * `integrations.checks.*`; parameters never contain secrets or response bodies.
 */
final readonly class CheckResult
{
    /**
     * @param  array<string, string|int>  $parameters
     */
    public function __construct(
        public CheckOutcome $outcome,
        public string $message,
        public array $parameters = [],
    ) {}

    public static function passed(): self
    {
        return new self(CheckOutcome::Passed, 'integrations.checks.passed');
    }

    /** @param  array<string, string|int>  $parameters */
    public static function failed(string $reason, array $parameters = []): self
    {
        return new self(CheckOutcome::Failed, "integrations.checks.{$reason}", $parameters);
    }

    /** @param  array<string, string|int>  $parameters */
    public static function unverified(string $reason, array $parameters = []): self
    {
        return new self(CheckOutcome::Unverified, "integrations.checks.{$reason}", $parameters);
    }
}
