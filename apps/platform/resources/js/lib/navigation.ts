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
    GraduationCap,
    House,
    Layers,
    LayoutGrid,
    Map as MapIcon,
    Megaphone,
    MessageSquare,
    Palette,
    Plug,
    Settings,
    ShieldCheck,
    ListChecks,
    Sparkles,
    Tag,
    Target,
    Users,
    Video,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { show as brandingShow } from '@/routes/branding';
import { index as brokerageIntegrationsIndex } from '@/routes/brokerage-integrations';
import { index as contactsIndex } from '@/routes/contacts';
import { index as agentsIndex } from '@/routes/agents';
import { settings as organisationSettings } from '@/routes/organisation';
import { index as complianceIndex } from '@/routes/compliance';
import { index as contentIndex } from '@/routes/content';
import { index as learn } from '@/routes/learn';
import { index as practice, readiness } from '@/routes/practice';
import { show as studyPlan } from '@/routes/study-plan';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as messagesIndex } from '@/routes/messages';
import { index as sessionsIndex } from '@/routes/sessions';
import { index as billingIndex } from '@/routes/billing';
import { index as gateways } from '@/routes/gateways';
import { index as plans } from '@/routes/plans';
import { show as aiSettings } from '@/routes/ai';
import { index as providerCategories } from '@/routes/provider-categories';
import { index as sales } from '@/routes/sales';
import { index as earnings } from '@/routes/earnings';
import { index as featuresIndex } from '@/routes/features';
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
import { index as schoolInstructors } from '@/routes/school/instructors';
import type { RoleName } from '@/types/auth';

/** Sidebar labels that exist in the translations (pages that are built). */
export type NavLabel =
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
    | 'instructors'
    | 'courseBuilder'
    | 'courseCatalogue'
    | 'aiLessons'
    | 'questionBank'
    | 'studyPlan'
    | 'practiceAndExams'
    | 'readiness';

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
            planned('ask'),
            planned('instructors'),
            planned('licenceJourney'),
            item('account', Settings, profile(), '/settings'),
        ],
        agent: [
            home,
            messages,
            item('contacts', Users, contactsIndex(), '/contacts'),
            planned('deals'),
            planned('virtualBroker'),
            planned('contentStudio'),
            item('learnAndCe', GraduationCap, learn(), '/learn'),
            planned('serviceProviders'),
            planned('goalsAndRewards'),
            item('connections', Plug, connections()),
            item('settings', Settings, profile(), '/settings'),
        ],
        provider: [
            home,
            planned('agentsInMyAreas'),
            messages,
            planned('profile'),
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
            planned('approvals'),
            item('agents', Users, agentsIndex()),
            planned('ceCompliance'),
            planned('virtualBroker'),
            planned('policies'),
            planned('mentorProgram'),
            planned('recruiting'),
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
            planned('mentorWorkspace'),
            item('earnings', Wallet, earnings()),
        ],
        instructor: [
            home,
            messages,
            // The course builder (A7.2, screen 43).
            item('courseBuilder', Layers, builderIndex(), '/course-builder'),
            // AI lesson review (screen 44) and question bank (screen 45), A8.1.
            item('aiLessons', Sparkles, reviewIndex(), '/ai-lessons'),
            item(
                'questionBank',
                ListChecks,
                questionsIndex(),
                '/question-bank',
            ),
            // Sessions and live classes (B6.3, flag `video_rooms`); the CE report follows.
            options.sessions
                ? item('liveClasses', Video, sessionsIndex(), '/sessions')
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
