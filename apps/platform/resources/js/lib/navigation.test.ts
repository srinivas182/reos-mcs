import { describe, expect, it } from 'vitest';
import { roleNavigation } from '@/lib/navigation';
import { toUrl } from '@/lib/utils';
import type { RoleName } from '@/types/auth';

const menu = (role: RoleName | null) =>
    roleNavigation(role).map((item) => [item.label, toUrl(item.href)]);

describe('roleNavigation (web journey, Navigation by role)', () => {
    it('gives the super admin the back office, Tenants and People included', () => {
        expect(menu('super_admin')).toEqual([
            ['overview', '/dashboard'],
            ['tenants', '/admin/tenants'],
            ['people', '/admin/people'],
            ['states', '/admin/states'],
            ['integrations', '/admin/integrations'],
            ['complianceAndAudit', '/admin/compliance'],
        ]);
    });

    it('ends the candidate menu with Account and the agent menu with Settings', () => {
        expect(menu('candidate').at(-1)).toEqual([
            'account',
            '/settings/profile',
        ]);
        expect(menu('agent').at(-1)).toEqual(['settings', '/settings/profile']);
    });

    it('starts every role with its dashboard and shows only built pages', () => {
        const roles: RoleName[] = [
            'candidate',
            'agent',
            'broker',
            'mentor',
            'instructor',
            'school_admin',
            'provider',
            'association_admin',
        ];

        for (const role of roles) {
            const items = menu(role);
            expect(items[0]).toEqual(['dashboard', '/dashboard']);
            expect(items.every(([, href]) => href.startsWith('/'))).toBe(true);
        }

        expect(menu(null)).toEqual([['dashboard', '/dashboard']]);
    });

    it('gives brokers Brokerage settings (screen 41)', () => {
        expect(menu('broker')).toContainEqual([
            'brokerageSettings',
            '/organisation/settings',
        ]);
    });
});
