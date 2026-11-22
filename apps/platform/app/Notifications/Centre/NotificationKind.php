<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

/**
 * Every kind of notification REOS sends (B6.2, ADR 0023), with its
 * channels on by default, whether it's urgent (it still arrives in quiet
 * hours), whether people may turn it off (security messages can't be) and
 * whether it can go into a digest.
 */
enum NotificationKind: string
{
    case NewDevice = 'new_device';
    case AccountDeletion = 'account_deletion';
    case PrivacyRequest = 'privacy_request';
    case PurchaseReceipt = 'purchase_receipt';
    case PaymentFailed = 'payment_failed';
    case Invitation = 'invitation';
    case MembershipLink = 'membership_link';
    case SeatsRequested = 'seats_requested';
    case BrokerAsked = 'broker_asked';
    case SupportAccess = 'support_access';
    case IntegrationPaused = 'integration_paused';
    case FlagRolledBack = 'flag_rolled_back';
    case MessagesReported = 'messages_reported';
    case StateRulesChanged = 'state_rules_changed';
    case StateWentLive = 'state_went_live';
    case ConnectionNeedsReconnect = 'connection_reconnect';
    case Message = 'message';
    case Announcement = 'announcement';
    case SessionStarting = 'session_starting';

    public const INBOX = 'inbox';

    public const EMAIL = 'email';

    /** The channels a person can choose between. */
    public const CHANNELS = [self::INBOX, self::EMAIL];

    /**
     * Where it goes unless the person changed it.
     *
     * @return list<string>
     */
    public function defaults(): array
    {
        return match ($this) {
            // Chats would flood email; they stay in the app.
            self::Message, self::Announcement => [self::INBOX],
            default => [self::INBOX, self::EMAIL],
        };
    }

    /** Arrives even in quiet hours (security, money that failed, things happening now). */
    public function urgent(): bool
    {
        return in_array($this, [self::NewDevice, self::AccountDeletion, self::PaymentFailed, self::SupportAccess, self::IntegrationPaused, self::FlagRolledBack, self::SessionStarting], true);
    }

    /** Security and account messages can't be turned off. */
    public function locked(): bool
    {
        return in_array($this, [self::NewDevice, self::AccountDeletion, self::PrivacyRequest, self::PurchaseReceipt, self::PaymentFailed, self::Invitation], true);
    }

    /** Can be gathered into a daily or weekly digest instead of one at a time. */
    public function digestible(): bool
    {
        return ! $this->urgent() && ! $this->locked();
    }

    /** The group it's listed under on the settings screen. */
    public function group(): string
    {
        return match ($this) {
            self::NewDevice, self::AccountDeletion, self::PrivacyRequest => 'account',
            self::PurchaseReceipt, self::PaymentFailed, self::MembershipLink => 'billing',
            self::Message, self::Announcement, self::MessagesReported => 'messages',
            self::SessionStarting, self::StateRulesChanged, self::StateWentLive => 'learning',
            self::Invitation, self::SeatsRequested, self::BrokerAsked, self::SupportAccess => 'organisation',
            self::IntegrationPaused, self::FlagRolledBack, self::ConnectionNeedsReconnect => 'tools',
        };
    }

    /**
     * Kinds that people receive (invitations go to people who aren't members yet).
     *
     * @return list<self>
     */
    public static function settable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $k): bool => $k !== self::Invitation));
    }
}
