import type { TFunction } from 'i18next';

import type { Me, Membership, Role } from '@/lib/auth';

/** "Licensed agent", "Broker"… in the person's language. */
export const roleName = (t: TFunction, role: Role): string => t(`roles.names.${role}`);

/** The membership in use on this phone, from `GET /me`. */
export function activeMembership(user: Me | null): Membership | null {
  if (!user?.active_role) {
    return null;
  }

  const { role, organisation_id: organisationId } = user.active_role;

  return user.roles.find((m) => m.role === role && m.organisation.id === organisationId) ?? null;
}

/** Each organisation once, in the order the roles come (most responsibility first). */
export function organisationsOf(user: Me | null): Membership['organisation'][] {
  const seen = new Map<number, Membership['organisation']>();

  for (const membership of user?.roles ?? []) {
    seen.set(membership.organisation.id, seen.get(membership.organisation.id) ?? membership.organisation);
  }

  return [...seen.values()];
}
