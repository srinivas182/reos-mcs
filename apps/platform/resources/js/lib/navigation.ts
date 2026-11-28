import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    CalendarDays,
    Building2,
    CreditCard,
    FileBadge,
    Flag,
    Gauge,
    FileCheck2,
    FileSearch,
    GraduationCap,
    House,
    Layers,
    LayoutGrid,
    Map as MapIcon,
    Megaphone,
    ClipboardCheck,
    TriangleAlert,
    IdCard,
    MessageCircleQuestion,
    MessageSquare,
    Palette,
    Plug,
    Settings,
    ShieldQuestion,
    ScrollText,
    ShieldCheck,
    ListChecks,
    Sparkles,
    Tag,
    Target,
    Users,
    Video,
    Wallet,
    Handshake,
    CalendarCheck,
    Milestone,
    UserPlus,
    Theater,
    Trophy,
    Gift,
    MessageSquareQuote,
    PartyPopper,
    DoorOpen,
    Send,
    MapPin,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { show as brandingShow } from '@/routes/branding';
import { index as brokerageIntegrationsIndex } from '@/routes/brokerage-integrations';
import { index as contactsIndex } from '@/routes/contacts';
import { index as dealsIndex } from '@/routes/deals';
import { index as agentsIndex } from '@/routes/agents';
import { settings as organisationSettings } from '@/routes/organisation';
import { index as complianceIndex } from '@/routes/compliance';
import { index as contentIndex } from '@/routes/content';
import { index as learn } from '@/routes/learn';
import { index as ceWallet } from '@/routes/ce';
import { index as studio } from '@/routes/studio';
import { index as ceReports } from '@/routes/school/ce-reports';
import { index as practice, progress, readiness } from '@/routes/practice';
import { show as studyPlan } from '@/routes/study-plan';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as messagesIndex } from '@/routes/messages';
import { index as billingIndex } from '@/routes/billing';
import { index as gateways } from '@/routes/gateways';
import { index as plans } from '@/routes/plans';
import { show as aiSettings } from '@/routes/ai';
import { show as pointsRates } from '@/routes/points';
import { index as rewardsIndex } from '@/routes/rewards';
import { index as feedbackIndex } from '@/routes/feedback';
import { index as rolePlayIndex } from '@/routes/role-play';
import { index as videoPracticeIndex } from '@/routes/video-practice';
import { index as openHousesIndex } from '@/routes/open-houses';
import { index as referralsIndex } from '@/routes/referrals';
import { index as farmingIndex } from '@/routes/farming';
import { index as candidateStudio } from '@/routes/candidate-studio';
import { index as complianceLog } from '@/routes/organisation/compliance-log';
import { index as brokerRewards } from '@/routes/organisation/rewards';
import { index as providerCategories } from '@/routes/provider-categories';
import { index as sales } from '@/routes/sales';
import { index as earnings } from '@/routes/earnings';
import { index as featuresIndex } from '@/routes/features';
import { index as askIndex } from '@/routes/ask';
import { index as bookingsIndex } from '@/routes/bookings';
import { index as classesIndex } from '@/routes/classes';
import { show as licenceJourney } from '@/routes/licence';
import { index as mentorsIndex } from '@/routes/mentors';
import { index as recruitingIndex } from '@/routes/recruiting';
import { index as virtualBroker } from '@/routes/virtual-broker';
import { index as contractReview } from '@/routes/contracts';
import { index as brokerVirtualBroker } from '@/routes/organisation/virtual-broker';
import { index as approvalsIndex } from '@/routes/approvals';
import { index as oversightIndex } from '@/routes/oversight';
import { index as policiesIndex } from '@/routes/policies';
import { show as providerJoin } from '@/routes/provider-join';
import { show as providerProfile } from '@/routes/provider-profile';
import { index as providersIndex } from '@/routes/providers';
import { index as mentorProgram } from '@/routes/organisation/mentors';
import { index as mentorWorkspace } from '@/routes/mentoring';
import { index as brokerChallenges } from '@/routes/organisation/challenges';
import { index as podIndex } from '@/routes/pod';
import { index as instructorQuestions } from '@/routes/instructor/questions';
import { index as connections } from '@/routes/connections';
import { index as integrations } from '@/routes/integrations';
import { index as people } from '@/routes/people';
import { edit as profile } from '@/routes/profile';
import { index as states } from '@/routes/states';
import { index as tenantsIndex } from '@/routes/tenants';
import { index as builderIndex } from '@/routes/builder';
import { index as questionsIndex } from '@/routes/builder/questions';
import { index as reviewIndex } from '@/routes/builder/review';
import { index as schoolApprovals } from '@/routes/school/approvals';
import { index as schoolStudents } from '@/routes/school/students';
import { index as schoolInstructors } from '@/routes/school/instructors';
import type { RoleName } from '@/types/auth';

/** Sidebar labels that exist in the translations (pages that are built). */
export type NavLabel =
    | 'ask'
    | 'instructors'
    | 'studentQuestions'
    | 'licenceJourney'
    | 'recruiting'
    | 'mentors'
    | 'deals'
    | 'openHouses'
    | 'referrals'
    | 'farming'
    | 'dashboard'
    | 'overview'
    | 'account'
    | 'settings'
    | 'people'
    | 'integrations'
    | 'states'
    | 'tenants'
    | 'brandingAndDomain'
    | 'brokerageSettings'
    | 'brokerageIntegrations'
    | 'contacts'
    | 'agents'
    | 'complianceAndAudit'
    | 'contentAndApprovals'
    | 'courses'
    | 'learnAndCe'
    | 'ceWallet'
    | 'contentStudio'
    | 'virtualBroker'
    | 'contractReview'
    | 'policies'
    | 'approvals'
    | 'oversight'
    | 'providerJoin'
    | 'serviceProviders'
    | 'profile'
    | 'mentorProgram'
    | 'mentorWorkspace'
    | 'podAndChallenges'
    | 'brokerRewards'
    | 'goalsAndRewards'
    | 'agentFeedback'
    | 'rolePlay'
    | 'videoPractice'
    | 'complianceLog'
    | 'candidateStudio'
    | 'pointsRates'
    | 'challenges'
    | 'ceReporting'
    | 'paymentGateways'
    | 'providerCategories'
    | 'aiAndCredits'
    | 'featuresAndReleases'
    | 'messages'
    | 'announcements'
    | 'salesAndPayouts'
    | 'earnings'
    | 'plansAndPricing'
    | 'planAndBilling'
    | 'connections'
    | 'liveClasses'
    | 'stateApprovals'
    | 'courseBuilder'
    | 'courseCatalogue'
    | 'aiLessons'
    | 'questionBank'
    | 'studyPlan'
    | 'practiceAndExams'
    | 'readiness'
    | 'progress'
    | 'students';

export type RoleNavItem = {
    /** Translation key under `nav.` (journey name, for items not built yet). */
    label: string;
    icon: LucideIcon;
    /** Null until the page is built; such items are not shown yet. */
    href: NonNullable<InertiaLinkProps['href']> | null;
    /** Highlight the item for every page under this path (default: href). */
    section?: string;
};

const item = (
    label: NavLabel,
    icon: LucideIcon,
    href: NonNullable<RoleNavItem['href']>,
    section?: string,
): RoleNavItem => ({ label, icon, href, section });

/** An item whose page exists: shown in the sidebar. */
export type BuiltNavItem = RoleNavItem & {
    label: NavLabel;
    href: NonNullable<RoleNavItem['href']>;
};

/** A journey item whose page comes in a later sprint (hidden until then). */
const planned = (label: string): RoleNavItem => ({
    label,
    icon: House,
    href: null,
});

/**
 * The left sidebar per role, in the order of the web journey (page 4,
 * "Navigation by role"). Items whose page isn't built yet have no `href`
 * and stay hidden; give them one in the sprint that builds the page.
 * The REOS super admin also has People (Sprint 3 spec, A3.2).
 */
export function roleNavigation(
    role: RoleName | null,
    options: { messages?: boolean; sessions?: boolean } = {},
): BuiltNavItem[] {
    const home = item('dashboard', House, dashboard());
    // Messages for every role once rolled out to the person (B6.1, flag `messages`).
    const messages = options.messages
        ? item('messages', MessageSquare, messagesIndex(), '/messages')
        : planned('messages');
    // Admins of brokerages, schools and associations announce (B6.1b), with Messages.
    const announcements = options.messages
        ? item(
              'announcements',
              Megaphone,
              announcementsIndex(),
              '/announcements',
          )
        : planned('announcements');

    const menus: Record<RoleName, RoleNavItem[]> = {
        candidate: [
            home,
            messages,
            item('courses', BookOpen, learn(), '/learn'),
            item('studyPlan', CalendarDays, studyPlan(), '/study-plan'),
            item('practiceAndExams', Target, practice(), '/practice'),
            item('readiness', Gauge, readiness(), '/readiness'),
            // Weekly progress report (A9.2e).
            item('progress', CalendarCheck, progress(), '/practice/progress'),
            // Ask, the study mate (A10.1).
            item('ask', MessageCircleQuestion, askIndex(), '/ask'),
            // Live classes for my courses (A10.2b, L12), with video sessions (flag `video_rooms`).
            ...(options.sessions
                ? [item('liveClasses', Video, classesIndex(), '/classes')]
                : []),
            // Book an instructor (A10.2a, screen 18).
            item('instructors', GraduationCap, bookingsIndex(), '/bookings'),
            // Free posts for study wins (B13.3), checked so nothing advertises services before the license.
            item(
                'candidateStudio',
                PartyPopper,
                candidateStudio(),
                '/candidate-studio',
            ),
            // The licence journey (A10.3a, screen 16).
            item(
                'licenceJourney',
                Milestone,
                licenceJourney(),
                '/licence-journey',
            ),
            item('account', Settings, profile(), '/settings'),
        ],
        agent: [
            home,
            messages,
            item('contacts', Users, contactsIndex(), '/contacts'),
            item('deals', Handshake, dealsIndex(), '/deals'),
            // Open houses (B14.3a): QR and phone sign-in, each visitor into the CRM.
            item('openHouses', DoorOpen, openHousesIndex(), '/open-houses'),
            // Referrals and invites (B14.3b): between brokers; invite and earn.
            item('referrals', Send, referralsIndex(), '/referrals'),
            // Farming (B14.3c): monthly market updates for an area, checked for compliance.
            item('farming', MapPin, farmingIndex(), '/farming'),
            // Contract review (A12.2, screen 15): TREC forms with dated findings and deadlines.
            item('contractReview', FileSearch, contractReview(), '/contracts'),
            // The virtual broker (A12.1, screen 16): policy answers with sources, the broker when needed.
            item(
                'virtualBroker',
                ShieldQuestion,
                virtualBroker(),
                '/virtual-broker',
            ),
            // Content studio (B11, screen 17): compose, check, publish or schedule, results.
            item('contentStudio', Megaphone, studio(), '/studio'),
            item('learnAndCe', GraduationCap, learn(), '/learn'),
            // Role-play and the skills assessment (A14.1a, screen 7).
            item('rolePlay', Theater, rolePlayIndex(), '/role-play'),
            // Video practice with an AI review (A14.1b).
            item(
                'videoPractice',
                Video,
                videoPracticeIndex(),
                '/video-practice',
            ),
            // Hours against the renewal, certificates and renewing (A11.2).
            item('ceWallet', Wallet, ceWallet(), '/ce'),
            // Mentors from the agent's own brokerage (A10.3c, f04).
            item('mentors', Users, mentorsIndex(), '/mentors'),
            // Accountability pod and brokerage challenges (A13.2, screen 131).
            item('podAndChallenges', Trophy, podIndex(), '/pod'),
            // Verified providers (B15.3): licence checked, areas and services.
            item('serviceProviders', Handshake, providersIndex(), '/providers'),
            // Daily goals, points and rewards (A13.3a, screens 111 and 112).
            item('goalsAndRewards', Gift, rewardsIndex(), '/rewards'),
            // Feedback from agents after verified closings (A13.3b, screen 125).
            item(
                'agentFeedback',
                MessageSquareQuote,
                feedbackIndex(),
                '/feedback',
            ),
            item('connections', Plug, connections()),
            item('settings', Settings, profile(), '/settings'),
        ],
        provider: [
            home,
            planned('agentsInMyAreas'),
            messages,
            // Joining and the profile agents see (B15.2 and B15.3, provider screens 1–7).
            item('providerJoin', ListChecks, providerJoin(), '/provider/join'),
            item('profile', IdCard, providerProfile(), '/provider/profile'),
            planned('community'),
            planned('results'),
            item(
                'planAndBilling',
                CreditCard,
                billingIndex(),
                '/settings/billing',
            ),
        ],
        broker: [
            home,
            messages,
            announcements,
            // One approvals queue (A15.3, screens 7–12): posts, pages and escalated questions.
            item(
                'approvals',
                ClipboardCheck,
                approvalsIndex(),
                '/organisation/approvals',
            ),
            item('agents', Users, agentsIndex()),
            // Agents needing help and deals at risk (A16.1).
            item(
                'oversight',
                TriangleAlert,
                oversightIndex(),
                '/organisation/oversight',
            ),
            planned('ceCompliance'),
            // Teach the virtual broker and answer agents (A12.1, screen 34).
            item(
                'virtualBroker',
                ShieldQuestion,
                brokerVirtualBroker(),
                '/organisation/virtual-broker',
            ),
            // Advertising policy (B12.1, screen 35): Texas rules, brokerage rules, brand kit, approvals.
            item(
                'policies',
                ScrollText,
                policiesIndex(),
                '/organisation/policies',
            ),
            // Compliance log (B13.2): every check, fix, approval and send-back, with CSV export.
            item(
                'complianceLog',
                ScrollText,
                complianceLog(),
                '/organisation/compliance-log',
            ),
            // Mentor program (A13.1a, screens 21–23): mentors, prices, points, assignment.
            item(
                'mentorProgram',
                GraduationCap,
                mentorProgram(),
                '/organisation/mentors',
            ),
            // Daily goals per mode and bonus campaigns (A13.3a).
            item(
                'brokerRewards',
                Gift,
                brokerRewards(),
                '/organisation/rewards',
            ),
            // Opt-in brokerage challenges (A13.2).
            item(
                'challenges',
                Trophy,
                brokerChallenges(),
                '/organisation/challenges',
            ),
            // Recruiting: candidates who asked for broker offers (A10.3b).
            item('recruiting', UserPlus, recruitingIndex(), '/recruiting'),
            planned('teamsAndRouting'),
            planned('reports'),
            item('brokerageIntegrations', Plug, brokerageIntegrationsIndex()),
            item('brokerageSettings', Building2, organisationSettings()),
        ],
        // Mentors work in the mentor workspace (web journey screen 48).
        // Earnings for mentors too, as for instructors (decision of 22 November 2026).
        mentor: [
            home,
            messages,
            // The mentor workspace (A13.1b, screen 48): Today, mentees, notes and goals.
            item('mentorWorkspace', Users, mentorWorkspace(), '/mentoring'),
            item('earnings', Wallet, earnings()),
        ],
        instructor: [
            home,
            messages,
            // The course builder (A7.2, screen 43).
            item('courseBuilder', Layers, builderIndex(), '/course-builder'),
            // Student questions (A10.1b, screens i09 and i10).
            item(
                'studentQuestions',
                MessageCircleQuestion,
                instructorQuestions(),
                '/instructor/questions',
            ),
            // AI lesson review (screen 44) and question bank (screen 45), A8.1.
            item('aiLessons', Sparkles, reviewIndex(), '/ai-lessons'),
            item(
                'questionBank',
                ListChecks,
                questionsIndex(),
                '/question-bank',
            ),
            // Live classes (A10.2b, w505 and i11), with all sessions a click away (B6.3, flag `video_rooms`).
            options.sessions
                ? item('liveClasses', Video, classesIndex(), '/classes')
                : planned('liveClasses'),
            planned('students'),
            planned('questions'),
            item('earnings', Wallet, earnings()),
        ],
        school_admin: [
            home,
            messages,
            announcements,
            item(
                'courseCatalogue',
                BookOpen,
                builderIndex(),
                '/course-builder',
            ),
            // Course catalogue and state approvals (A7.1, screen 50).
            item(
                'stateApprovals',
                FileBadge,
                schoolApprovals(),
                '/school/approvals',
            ),
            // CE reporting to TREC (A11.3, screen 51).
            item('ceReporting', FileCheck2, ceReports(), '/school/ce-reports'),
            // Weekly reports candidates shared with the school (A9.2e).
            item('students', Users, schoolStudents(), '/school/students'),
            planned('ceReporting'),
            // Instructors and avatar consent (A7.1b, screen 52).
            item(
                'instructors',
                GraduationCap,
                schoolInstructors(),
                '/school/instructors',
            ),
            planned('students'),
            item('salesAndPayouts', CreditCard, sales(), '/organisation/sales'),
            planned('schoolSettings'),
        ],
        association_admin: [
            home,
            messages,
            planned('members'),
            planned('memberCe'),
            planned('events'),
            announcements,
            planned('programs'),
            item('brandingAndDomain', Palette, brandingShow()),
            planned('reports'),
        ],
        super_admin: [
            item('overview', House, dashboard()),
            item('tenants', Building2, tenantsIndex()),
            item('people', Users, people()),
            item('states', MapIcon, states()),
            item(
                'contentAndApprovals',
                BookOpen,
                contentIndex(),
                '/admin/content',
            ),
            item('integrations', Plug, integrations()),
            item('paymentGateways', CreditCard, gateways(), '/admin/gateways'),
            item('plansAndPricing', Tag, plans(), '/admin/plans'),
            item(
                'providerCategories',
                LayoutGrid,
                providerCategories(),
                '/admin/provider-categories',
            ),
            item('aiAndCredits', Sparkles, aiSettings(), '/admin/ai'),
            // What daily goals earn (A13.3a).
            item('pointsRates', Gift, pointsRates(), '/admin/points'),
            item(
                'featuresAndReleases',
                Flag,
                featuresIndex(),
                '/admin/features',
            ),
            planned('platformHealth'),
            item('complianceAndAudit', ShieldCheck, complianceIndex()),
        ],
    };

    return (role === null ? [home] : menus[role]).filter(
        (entry): entry is BuiltNavItem => entry.href !== null,
    );
}
