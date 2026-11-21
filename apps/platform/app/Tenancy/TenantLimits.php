<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\OrganisationType;

/**
 * Limits and data for a tenant (screen 60): seats and who pays, AI credits,
 * the API rate limit, fair-queue weight and retention. Stored as JSON on the
 * organisation; anything not set uses the defaults for its type.
 */
final readonly class TenantLimits
{
    public const PAYER_ORGANISATION = 'organisation';

    public const PAYER_MEMBER = 'member';

    public const QUEUE_WEIGHTS = ['low', 'standard', 'high'];

    public function __construct(
        /** Paid seats; null means no limit. */
        public ?int $seats,
        /** `organisation` (for example "brokerage pays") or `member`. */
        public string $payer,
        public int $creditsPerSeat,
        /** Credits are shared across the tenant's seats. */
        public bool $creditsPooled,
        /** Credits the tenant can sponsor for candidates. */
        public int $candidatePool,
        /** API requests per minute for the whole tenant. */
        public int $rateLimit,
        public string $queueWeight,
        public int $logRetentionYears,
        public int $deletedUserDays,
    ) {}

    /** @param  array<string, mixed>|null  $stored */
    public static function for(OrganisationType $type, ?array $stored): self
    {
        $defaults = self::defaults($type);
        $stored ??= [];

        $int = static fn (string $key, ?int $fallback): ?int => array_key_exists($key, $stored)
            ? (is_numeric($stored[$key]) ? (int) $stored[$key] : null)
            : $fallback;

        return new self(
            seats: $int('seats', $defaults->seats),
            payer: in_array($stored['payer'] ?? null, [self::PAYER_ORGANISATION, self::PAYER_MEMBER], true) ? (string) $stored['payer'] : $defaults->payer,
            creditsPerSeat: $int('credits_per_seat', $defaults->creditsPerSeat) ?? $defaults->creditsPerSeat,
            creditsPooled: is_bool($stored['credits_pooled'] ?? null) ? $stored['credits_pooled'] : $defaults->creditsPooled,
            candidatePool: $int('candidate_pool', $defaults->candidatePool) ?? $defaults->candidatePool,
            rateLimit: $int('rate_limit', $defaults->rateLimit) ?? $defaults->rateLimit,
            queueWeight: in_array($stored['queue_weight'] ?? null, self::QUEUE_WEIGHTS, true) ? (string) $stored['queue_weight'] : $defaults->queueWeight,
            logRetentionYears: $int('log_retention_years', $defaults->logRetentionYears) ?? $defaults->logRetentionYears,
            deletedUserDays: $int('deleted_user_days', $defaults->deletedUserDays) ?? $defaults->deletedUserDays,
        );
    }

    public static function defaults(OrganisationType $type): self
    {
        return new self(
            seats: match ($type) {
                OrganisationType::Brokerage => 25,
                OrganisationType::Team => null,
                OrganisationType::School => 10,
                OrganisationType::ProviderBusiness => 5,
                OrganisationType::Association, OrganisationType::Platform => null,
            },
            payer: $type === OrganisationType::ProviderBusiness ? self::PAYER_MEMBER : self::PAYER_ORGANISATION,
            creditsPerSeat: 300,
            creditsPooled: true,
            candidatePool: 0,
            rateLimit: 600,
            queueWeight: 'standard',
            logRetentionYears: 7,
            deletedUserDays: 30,
        );
    }

    /** @return array<string, int|bool|string|null> */
    public function toArray(): array
    {
        return [
            'seats' => $this->seats,
            'payer' => $this->payer,
            'credits_per_seat' => $this->creditsPerSeat,
            'credits_pooled' => $this->creditsPooled,
            'candidate_pool' => $this->candidatePool,
            'rate_limit' => $this->rateLimit,
            'queue_weight' => $this->queueWeight,
            'log_retention_years' => $this->logRetentionYears,
            'deleted_user_days' => $this->deletedUserDays,
        ];
    }
}
