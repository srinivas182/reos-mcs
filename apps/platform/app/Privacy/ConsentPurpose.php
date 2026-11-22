<?php

declare(strict_types=1);

namespace App\Privacy;

use App\Authorization\Role;

/**
 * Everything REOS asks a person to agree to (A6.3). Each purpose has the
 * version of the words they agreed to; a new version of the terms or the
 * privacy notice asks again. Some purposes are agreed per thing (a session
 * to record, a provider to share contacts with); some are switches on the
 * privacy settings; the terms and privacy notice can only be withdrawn by
 * deleting the account.
 */
enum ConsentPurpose: string
{
    case Terms = 'terms';
    case PrivacyNotice = 'privacy_notice';
    case MarketingEmail = 'marketing_email';
    case Sms = 'sms';
    case SessionRecording = 'session_recording';
    case ContactSharing = 'contact_sharing';
    case AiPersonalData = 'ai_personal_data';
    case ProviderNetwork = 'provider_network';
    case Recruiting = 'recruiting';

    /** Change a version when its words change; people are asked again. */
    private const VERSIONS = [
        'terms' => '2026-11-22',
        'privacy_notice' => '2026-11-22',
    ];

    /** The version of the words in force (the date they were published). */
    public function version(): string
    {
        // The terms and privacy notice are published at /terms and /privacy;
        // the other purposes' words are on the screen that asks.
        return self::VERSIONS[$this->value] ?? '2026-11-22';
    }

    /** Agreed when signing up; withdrawn only by deleting the account. */
    public function required(): bool
    {
        return in_array($this, [self::Terms, self::PrivacyNotice], true);
    }

    /** Agreed for one thing at a time (a session, a provider), never as a switch. */
    public function perSubject(): bool
    {
        return in_array($this, [self::SessionRecording, self::ContactSharing], true);
    }

    /** Shown as a switch on the privacy settings (web and phone). */
    public function switchable(): bool
    {
        return ! $this->required() && ! $this->perSubject();
    }

    /**
     * What the switch shows before the person has chosen. Only these may be
     * "on" without a record; everything else needs the person's yes.
     * Prospects (candidates) are in the provider network and not open to
     * recruiting until they say so (S06 spec, A6.3).
     */
    public function defaultFor(?Role $role): bool
    {
        return match ($this) {
            self::ProviderNetwork => $role === Role::Candidate,
            self::AiPersonalData => true,
            default => false,
        };
    }

    /** @return list<self> */
    public static function switches(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $p): bool => $p->switchable()));
    }
}
