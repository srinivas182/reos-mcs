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
    case SessionBooked = 'session_booked';
    case SessionStarting = 'session_starting';
    case ApprovalExpiring = 'approval_expiring';
    case AvatarConsentEnded = 'avatar_consent_ended';
    case ContentReview = 'content_review';
    /** The candidate's weekly progress report, Sunday evening (A9.2e). */
    case WeeklyReport = 'weekly_report';
    /** A challenge's result, or one sent to the candidate's study group (A9.3). */
    case Challenge = 'challenge';
    /** A deal's key date is 72 or 24 hours away (B9.1). */
    case DealDeadline = 'deal_deadline';
    /** A student's question for a course's instructors (A10.1b). */
    case StudentQuestion = 'student_question';
    /** The instructor answered the student's question (A10.1b). */
    case InstructorAnswer = 'instructor_answer';
    /** Everyone signed an envelope sent from a deal (B9.3). */
    case SignatureCompleted = 'signature_completed';
    /** A live class's attendance check is still unanswered (A10.2b). */
    case ClassCheck = 'class_check';
    /** A CE class report is ready to confirm, or was sent to the school (A10.2c). */
    case ClassReport = 'class_report';
    /** The licence exam: a week and a day before, then enter the result (A10.3a). */
    case ExamReminder = 'exam_reminder';
    /** Someone in the study group or school passed, or congrats on your pass (A10.3b). */
    case PassNews = 'pass_news';
    /** A brokerage's offer, or a candidate's answer to one (A10.3b). */
    case BrokerOffer = 'broker_offer';
    /** The candidate's licence is active on the regulator's record: switch to agent (A10.3c). */
    case LicenceFound = 'licence_found';
    /** The agent's morning brief is ready, at their chosen time (A11.1c). */
    case MorningBrief = 'morning_brief';
    /** CE hours missing 60, 30 or 7 days before renewal, or the renewal is ready (A11.2). */
    case CeRenewal = 'ce_renewal';
    /** An agent's question went to the broker (A12.1): money, legal or unclear. */
    case PolicyQuestion = 'policy_question';
    /** The broker answered the agent's virtual broker question (A12.1). */
    case PolicyAnswer = 'policy_answer';
    /** A contract review is done, or couldn't be done (A12.2). */
    case ContractReview = 'contract_review';
    /** An agent's post waits for the broker's approval (B12.1). */
    case PostApproval = 'post_approval';
    /** The broker approved an agent's post or sent it back (B12.1). */
    case PostDecision = 'post_decision';
    /** A milestone made into a free post, at most one a day (B12.3). */
    case Celebration = 'celebration';
    /** A mentor assigned, or a change asked for (A13.1a). */
    case Mentoring = 'mentoring';
    /** Another agent rated you after a closing (A13.3b). */
    case AgentFeedback = 'agent_feedback';
    /** Someone signed in at an open house or wrote from the agent's page (B14.3a). */
    case NewLead = 'new_lead';
    /** A referral from another agent, or their answer to one (B14.3b). */
    case Referral = 'referral';
    /** Someone you invited got their license: points (B14.3b). */
    case InviteReward = 'invite_reward';
    /** An approval or escalated question waited past the brokerage's working hours (A15.3). */
    case ApprovalOverdue = 'approval_overdue';

    public const INBOX = 'inbox';

    public const PUSH = 'push';

    public const EMAIL = 'email';

    public const SMS = 'sms';

    /** The channels a person can choose between (texts only once they've turned texts on). */
    public const CHANNELS = [self::INBOX, self::PUSH, self::EMAIL, self::SMS];

    /**
     * Where it goes unless the person changed it. Never texts: those need
     * the person to choose each kind after turning texts on.
     *
     * @return list<string>
     */
    public function defaults(): array
    {
        return match ($this) {
            // Chats would flood email and the inbox; the Messages badge already counts them.
            self::Message => [self::PUSH],
            self::Announcement => [self::INBOX, self::PUSH],
            // A tap opens the brief; email would arrive after the morning has started.
            self::MorningBrief => [self::PUSH],
            self::Celebration, self::AgentFeedback, self::InviteReward => [self::INBOX, self::PUSH],
            default => [self::INBOX, self::PUSH, self::EMAIL],
        };
    }

    /** Arrives even in quiet hours (security, money that failed, things happening now). */
    public function urgent(): bool
    {
        return in_array($this, [self::NewDevice, self::AccountDeletion, self::PaymentFailed, self::SupportAccess, self::IntegrationPaused, self::FlagRolledBack, self::SessionStarting, self::ClassCheck], true);
    }

    /** Security and account messages can't be turned off. */
    public function locked(): bool
    {
        return in_array($this, [self::NewDevice, self::AccountDeletion, self::PrivacyRequest, self::PurchaseReceipt, self::PaymentFailed, self::Invitation], true);
    }

    /** Can be gathered into a daily or weekly digest instead of one at a time. */
    public function digestible(): bool
    {
        return ! $this->urgent() && ! $this->locked() && $this !== self::MorningBrief;
    }

    /** The group it's listed under on the settings screen. */
    public function group(): string
    {
        return match ($this) {
            self::NewDevice, self::AccountDeletion, self::PrivacyRequest => 'account',
            self::PurchaseReceipt, self::PaymentFailed, self::MembershipLink => 'billing',
            self::Message, self::Announcement, self::MessagesReported => 'messages',
            self::SessionBooked, self::SessionStarting, self::ClassCheck, self::ClassReport, self::ExamReminder, self::PassNews, self::BrokerOffer, self::LicenceFound, self::CeRenewal, self::StateRulesChanged, self::StateWentLive, self::WeeklyReport, self::Challenge, self::StudentQuestion, self::InstructorAnswer, self::Mentoring => 'learning',
            self::Invitation, self::SeatsRequested, self::BrokerAsked, self::SupportAccess, self::ApprovalExpiring, self::AvatarConsentEnded, self::ContentReview, self::PolicyQuestion, self::PostApproval, self::ApprovalOverdue => 'organisation',
            self::IntegrationPaused, self::FlagRolledBack, self::ConnectionNeedsReconnect => 'tools',
            self::DealDeadline, self::SignatureCompleted, self::MorningBrief, self::PolicyAnswer, self::ContractReview, self::PostDecision, self::Celebration, self::AgentFeedback, self::NewLead, self::Referral, self::InviteReward => 'deals',
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
