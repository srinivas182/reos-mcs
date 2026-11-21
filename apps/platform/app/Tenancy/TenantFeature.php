<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\OrganisationType;

/**
 * Feature switches per tenant (screen 60). A tenant's own choice is stored
 * in `organisations.features`; without one, the default below applies.
 * Beta features are switched on from Features and releases (screen 70), not
 * per tenant. Each feature checks `Organisation::hasFeature()` when it ships.
 */
enum TenantFeature: string
{
    case VirtualBroker = 'virtual_broker';
    case ContractReview = 'contract_review';
    case ProviderMarketplace = 'provider_marketplace';
    case RecruitingAgents = 'recruiting_agents';
    case AiVideoLessons = 'ai_video_lessons';
    case SpanishUi = 'spanish_ui';

    public const AVAILABILITY_DEFAULT = 'default';

    public const AVAILABILITY_BETA = 'beta';

    public const AVAILABILITY_ADD_ON = 'add_on';

    /** `default`, `beta` (not switchable here) or `add_on` (part of a paid add-on). */
    public function availability(): string
    {
        return match ($this) {
            self::RecruitingAgents => self::AVAILABILITY_BETA,
            self::AiVideoLessons => self::AVAILABILITY_ADD_ON,
            default => self::AVAILABILITY_DEFAULT,
        };
    }

    public function defaultFor(OrganisationType $type): bool
    {
        return match ($this) {
            self::VirtualBroker => in_array($type, [OrganisationType::Brokerage, OrganisationType::Team], true),
            self::ContractReview => $type !== OrganisationType::School,
            self::ProviderMarketplace, self::SpanishUi => true,
            self::RecruitingAgents, self::AiVideoLessons => false,
        };
    }

    public function switchable(): bool
    {
        return $this->availability() !== self::AVAILABILITY_BETA;
    }
}
