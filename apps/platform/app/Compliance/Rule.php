<?php

declare(strict_types=1);

namespace App\Compliance;

/**
 * One advertising rule, as data (B13.1, ADR 0057): what it checks, where
 * the rule comes from, the channels it applies to, whether it blocks or
 * only warns, and how to fix it (an i18n key).
 */
final readonly class Rule
{
    /**
     * @param  list<string>  $channels  from ComplianceEngine::CHANNELS
     * @param  'block'|'warn'  $severity
     */
    public function __construct(
        public string $code,
        public string $citation,
        public string $url,
        public array $channels,
        public string $severity,
        public string $fix,
    ) {}

    public function appliesTo(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    /** @return array{code: string, citation: string, url: string, channels: list<string>, severity: 'block'|'warn', fix: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'citation' => $this->citation, 'url' => $this->url, 'channels' => $this->channels, 'severity' => $this->severity, 'fix' => $this->fix];
    }
}
