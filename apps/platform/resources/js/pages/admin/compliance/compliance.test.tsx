import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import Compliance from '@/pages/admin/compliance/index';

const router = vi.hoisted(() => ({
    patch: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/admin/compliance', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const props = {
    privacy: [
        {
            id: 3,
            kind: 'correct' as const,
            person: 'K. Soto',
            details: 'Wrong licence number',
            status: 'open' as const,
            due_at: '2026-12-12T10:00:00Z',
            overdue: false,
            extended: false,
            created_at: '2026-10-28T10:00:00Z',
            response: null,
        },
        {
            id: 2,
            kind: 'delete' as const,
            person: 'A. Lee',
            details: null,
            status: 'hold' as const,
            due_at: '2026-12-10T10:00:00Z',
            overdue: false,
            extended: false,
            created_at: '2026-10-26T10:00:00Z',
            response: null,
        },
        {
            id: 1,
            kind: 'access' as const,
            person: 'Rob Tan',
            details: null,
            status: 'done' as const,
            due_at: '2026-12-04T10:00:00Z',
            overdue: false,
            extended: false,
            created_at: '2026-10-20T10:00:00Z',
            response: null,
        },
    ],
    limitDays: 45,
    dataMapReviewed: '2026-11-21',
    audit: {
        rows: [
            {
                id: 9,
                at: '2026-11-21T09:42:00Z',
                actor: 'Srini',
                action: 'integration.paused',
                tenant: null,
                before: null,
                after: null,
                ip: '203.0.113.7',
            },
            {
                id: 8,
                at: '2026-11-21T09:30:00Z',
                actor: 'Linda Park',
                action: 'brand.updated',
                tenant: 'Lone Star Realty',
                before: { primary: null },
                after: { primary: '#1D4ED8' },
                ip: null,
            },
            {
                id: 7,
                at: '2026-11-21T08:15:00Z',
                actor: null,
                action: 'state.status_changed',
                tenant: null,
                before: 'coming_soon',
                after: 'in_preparation',
                ip: null,
            },
        ],
        next: '/admin/compliance?cursor=abc',
        previous: null,
    },
    filters: { q: null, group: null, tenant: null, from: null, to: null },
    options: {
        groups: ['sign_in', 'tenants', 'brand'] as const,
        tenants: [{ id: 5, name: 'Lone Star Realty' }],
    },
};

beforeEach(() => vi.clearAllMocks());

describe('Compliance and audit (screen 71)', () => {
    it('shows privacy requests with their status and the 45-day limit', () => {
        render(
            <Compliance
                {...props}
                options={{
                    ...props.options,
                    groups: [...props.options.groups],
                }}
            />,
        );

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Compliance and audit',
            }),
        ).toBeInTheDocument();
        const deletion = screen.getByRole('row', { name: /Delete account/ });
        expect(
            within(deletion).getByText('In 30-day hold'),
        ).toBeInTheDocument();
        expect(screen.getByText('45 days')).toBeInTheDocument();
        expect(
            screen.getByText('Two-step sign-in for every admin role'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Export audit' }),
        ).toHaveAttribute('href', '/admin/compliance/audit.csv');
    });

    it('starts a request, and asks for a reason before declining', async () => {
        render(
            <Compliance
                {...props}
                options={{
                    ...props.options,
                    groups: [...props.options.groups],
                }}
            />,
        );

        const manage = () =>
            screen.getByRole('button', {
                name: 'Manage Correct data for K. Soto',
            });
        expect(
            screen.queryByRole('button', {
                name: 'Manage Delete account for A. Lee',
            }),
        ).not.toBeInTheDocument();

        await userEvent.click(manage());
        await userEvent.click(screen.getByRole('menuitem', { name: 'Start' }));
        expect(router.patch).toHaveBeenCalledWith(
            '/admin/compliance/privacy/3',
            { action: 'start' },
            expect.anything(),
        );

        await userEvent.click(manage());
        await userEvent.click(
            screen.getByRole('menuitem', { name: 'Decline' }),
        );
        expect(
            screen.getByRole('dialog', { name: 'Decline the request' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Note for the person')).toBeRequired();
    });

    it('lists audit events with who, what changed and the tenant, and pages back', () => {
        render(
            <Compliance
                {...props}
                options={{
                    ...props.options,
                    groups: [...props.options.groups],
                }}
            />,
        );

        const brand = screen.getByRole('row', { name: /Changed the brand/ });
        expect(within(brand).getByText('Linda Park')).toBeInTheDocument();
        expect(
            within(brand).getByText('primary: — → #1D4ED8'),
        ).toBeInTheDocument();
        expect(within(brand).getByText('Lone Star Realty')).toBeInTheDocument();
        expect(
            screen.getByRole('row', { name: /Changed a state's status/ }),
        ).toHaveTextContent('System');
        expect(screen.getByRole('link', { name: 'Older' })).toHaveAttribute(
            'href',
            '/admin/compliance?cursor=abc',
        );
    });
});
