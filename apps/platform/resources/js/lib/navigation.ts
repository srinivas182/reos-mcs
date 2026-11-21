import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { House, Map as MapIcon, Plug, Settings, Users } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as integrations } from '@/routes/integrations';
import { index as people } from '@/routes/people';
import { edit as profile } from '@/routes/profile';
import { index as states } from '@/routes/states';
import type { RoleName } from '@/types/auth';

/** Sidebar labels that exist in the translations (pages that are built). */
export type NavLabel =
    | 'dashboard'
    | 'overview'
    | 'account'
    | 'settings'
    | 'people'
    | 'integrations'
    | 'states';

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
            planned('courses'),
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
            planned('learnAndCe'),
            planned('serviceProviders'),
            planned('goalsAndRewards'),
            planned('connections'),
            item('settings', Settings, profile(), '/settings'),
        ],
        provider: [
            home,
            planned('agentsInMyAreas'),
            planned('messages'),
            planned('profile'),
            planned('community'),
            planned('results'),
            planned('planAndBilling'),
        ],
        broker: [
            home,
            planned('approvals'),
            planned('agents'),
            planned('ceCompliance'),
            planned('virtualBroker'),
            planned('policies'),
            planned('mentorProgram'),
            planned('recruiting'),
            planned('teamsAndRouting'),
            planned('reports'),
            planned('brokerageIntegrations'),
            planned('brokerageSettings'),
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
            planned('earnings'),
        ],
        school_admin: [
            home,
            planned('courseCatalogue'),
            planned('stateApprovals'),
            planned('ceReporting'),
            planned('instructors'),
            planned('students'),
            planned('salesAndPayouts'),
            planned('schoolSettings'),
        ],
        association_admin: [
            home,
            planned('members'),
            planned('memberCe'),
            planned('events'),
            planned('announcements'),
            planned('programs'),
            planned('brandingAndDomain'),
            planned('reports'),
        ],
        super_admin: [
            item('overview', House, dashboard()),
            planned('tenants'),
            item('people', Users, people()),
            item('states', MapIcon, states()),
            planned('contentAndApprovals'),
            item('integrations', Plug, integrations()),
            planned('paymentGateways'),
            planned('plansAndPricing'),
            planned('providerCategories'),
            planned('aiAndCredits'),
            planned('featuresAndReleases'),
            planned('platformHealth'),
            planned('complianceAndAudit'),
        ],
    };

    return (role === null ? [home] : menus[role]).filter(
        (entry): entry is BuiltNavItem => entry.href !== null,
    );
}
