import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    Building2,
    CreditCard,
    GraduationCap,
    House,
    Map as MapIcon,
    Palette,
    Plug,
    Settings,
    ShieldCheck,
    Tag,
    Users,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { show as brandingShow } from '@/routes/branding';
import { index as agentsIndex } from '@/routes/agents';
import { settings as organisationSettings } from '@/routes/organisation';
import { index as complianceIndex } from '@/routes/compliance';
import { index as contentIndex } from '@/routes/content';
import { index as learn } from '@/routes/learn';
import { index as billingIndex } from '@/routes/billing';
import { index as gateways } from '@/routes/gateways';
import { index as plans } from '@/routes/plans';
import { index as sales } from '@/routes/sales';
import { index as earnings } from '@/routes/earnings';
import { index as connections } from '@/routes/connections';
import { index as integrations } from '@/routes/integrations';
import { index as people } from '@/routes/people';
import { edit as profile } from '@/routes/profile';
import { index as states } from '@/routes/states';
import { index as tenantsIndex } from '@/routes/tenants';
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
    | 'agents'
    | 'complianceAndAudit'
    | 'contentAndApprovals'
    | 'courses'
    | 'learnAndCe'
    | 'paymentGateways'
    | 'salesAndPayouts'
    | 'earnings'
    | 'plansAndPricing'
    | 'planAndBilling'
    | 'connections';

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
export function roleNavigation(role: RoleName | null): BuiltNavItem[] {
    const home = item('dashboard', House, dashboard());

    const menus: Record<RoleName, RoleNavItem[]> = {
        candidate: [
            home,
            item('courses', BookOpen, learn(), '/learn'),
            planned('studyPlan'),
            planned('practiceAndExams'),
            planned('readiness'),
            planned('ask'),
            planned('instructors'),
            planned('licenceJourney'),
            item('account', Settings, profile(), '/settings'),
        ],
        agent: [
            home,
            planned('contacts'),
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
            planned('messages'),
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
            planned('approvals'),
            item('agents', Users, agentsIndex()),
            planned('ceCompliance'),
            planned('virtualBroker'),
            planned('policies'),
            planned('mentorProgram'),
            planned('recruiting'),
            planned('teamsAndRouting'),
            planned('reports'),
            planned('brokerageIntegrations'),
            item('brokerageSettings', Building2, organisationSettings()),
        ],
        // Mentors work in the mentor workspace (web journey screen 48).
        mentor: [home, planned('mentorWorkspace')],
        instructor: [
            home,
            planned('courseBuilder'),
            planned('aiLessons'),
            planned('questionBank'),
            planned('liveClasses'),
            planned('students'),
            planned('questions'),
            item('earnings', Wallet, earnings()),
        ],
        school_admin: [
            home,
            planned('courseCatalogue'),
            planned('stateApprovals'),
            planned('ceReporting'),
            planned('instructors'),
            planned('students'),
            item('salesAndPayouts', CreditCard, sales(), '/organisation/sales'),
            planned('schoolSettings'),
        ],
        association_admin: [
            home,
            planned('members'),
            planned('memberCe'),
            planned('events'),
            planned('announcements'),
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
            planned('providerCategories'),
            planned('aiAndCredits'),
            planned('featuresAndReleases'),
            planned('platformHealth'),
            item('complianceAndAudit', ShieldCheck, complianceIndex()),
        ],
    };

    return (role === null ? [home] : menus[role]).filter(
        (entry): entry is BuiltNavItem => entry.href !== null,
    );
}
