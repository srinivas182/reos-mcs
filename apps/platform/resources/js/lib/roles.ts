import type { TFunction } from 'i18next';
import type { Membership, Roles } from '@/types/auth';

/** "Broker", "School admin"… in the person's language. */
export function roleName(t: TFunction, role: Membership['role']): string {
    return t(`roles.names.${role}`);
}

/**
 * "Broker · Lone Star Realty", or "Agent · on your own" for someone who
 * holds the role without a brokerage, school or business.
 */
export function membershipLabel(t: TFunction, membership: Membership): string {
    const role = roleName(t, membership.role);

    if (
        membership.organisation.type === 'platform' &&
        membership.role !== 'super_admin'
    ) {
        return t('roles.switcher.onOwn', { role });
    }

    return `${role} · ${membership.organisation.name}`;
}

/** The membership in use, from the shared `auth.roles` prop. */
export function activeMembership(roles: Roles | null): Membership | null {
    if (roles === null || roles.active === null) {
        return null;
    }

    const { role, organisationId } = roles.active;

    return (
        roles.held.find(
            (m) => m.role === role && m.organisation.id === organisationId,
        ) ?? null
    );
}

export function isSameMembership(a: Membership, b: Membership): boolean {
    return a.role === b.role && a.organisation.id === b.organisation.id;
}
