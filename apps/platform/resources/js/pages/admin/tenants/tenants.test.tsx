import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { loneStar, tenantOptions } from '@/components/tenant-fixtures';
import { i18n } from '@/lib/i18n';
import TenantsIndex from '@/pages/admin/tenants/index';
import TenantShow from '@/pages/admin/tenants/show';

const router = vi.hoisted(() => ({
    get: vi.fn(),
    patch: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/admin/tenants/3/features', props: {} }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const none = { type: null, status: null, state: null, search: null };
const noSupport = { can_ask: true, latest: null };

beforeEach(() => vi.clearAllMocks());

describe('Tenants (screen 59)', () => {
    it('lists tenants with type, plan, people, domain, cell and status', () => {
        render(
            <TenantsIndex
                filters={none}
                options={tenantOptions}
                tenants={[
                    loneStar,
                    {
                        ...loneStar,
                        id: 9,
                        name: 'Okoye Team',
                        type: 'team',
                        parent: 'Lone Star Realty',
                        plan: 'inherits',
                        seats: null,
                        members: 12,
                        domain: null,
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('heading', { level: 1, name: 'Tenants' }),
        ).toBeInTheDocument();
        const rows = screen.getAllByRole('row');
        expect(
            within(rows[1]!).getByRole('link', { name: /Lone Star Realty/ }),
        ).toHaveAttribute('href', '/admin/tenants/3');
        expect(
            within(rows[1]!).getByText('Brokerage · 142 seats'),
        ).toBeInTheDocument();
        expect(
            within(rows[1]!).getByText('lone-star-realty.reos.com'),
        ).toBeInTheDocument();
        expect(within(rows[1]!).getByText('Live')).toBeInTheDocument();
        expect(
            within(rows[2]!).getByText('Team · in Lone Star Realty'),
        ).toBeInTheDocument();
        expect(within(rows[2]!).getByText('Inherits')).toBeInTheDocument();
        expect(
            screen.getByText(
                /Row-level security keeps every tenant's data separate/,
            ),
        ).toBeInTheDocument();
    });

    it('exports with the filters on screen, and explains an empty result', () => {
        render(
            <TenantsIndex
                filters={{ ...none, type: 'school' }}
                options={tenantOptions}
                tenants={[]}
            />,
        );

        expect(
            screen.getByRole('link', { name: 'Export CSV' }),
        ).toHaveAttribute('href', '/admin/tenants/export?type=school');
        expect(screen.getByText('No tenants match')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Clear filters' }),
        ).toBeInTheDocument();
    });
});

describe('Tenant detail (screen 60)', () => {
    it('saves only what changed', async () => {
        render(
            <TenantShow
                tab="features"
                tenant={loneStar}
                audit={[]}
                options={tenantOptions}
                support={noSupport}
            />,
        );

        expect(
            screen.getByRole('heading', { level: 1, name: 'Lone Star Realty' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /Brokerage tenant · created .* · owner Linda Park/,
            ),
        ).toBeInTheDocument();
        const save = screen.getByRole('button', { name: 'Save' });
        expect(save).toBeDisabled();

        await userEvent.click(
            screen.getByRole('switch', {
                name: 'AI video lessons for brokerage training',
            }),
        );
        expect(save).toBeEnabled();
        await userEvent.click(save);

        expect(router.patch).toHaveBeenCalledWith(
            '/admin/tenants/3',
            { features: { ai_video_lessons: true } },
            expect.anything(),
        );
    });

    it('asks for a reason before suspending', async () => {
        router.put.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onError: (e: Record<string, string>) => void },
            ) =>
                options.onError({
                    reason: 'The reason field is required when status is suspended.',
                }),
        );
        render(
            <TenantShow
                tab="overview"
                tenant={loneStar}
                audit={[]}
                options={tenantOptions}
                support={noSupport}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Suspend' }));
        const dialog = within(screen.getByRole('dialog'));
        expect(
            dialog.getByText(/Its people lose access to it straight away/),
        ).toBeInTheDocument();
        await userEvent.click(dialog.getByRole('button', { name: 'Suspend' }));

        expect(router.put).toHaveBeenCalledWith(
            '/admin/tenants/3/status',
            { status: 'suspended', reason: '' },
            expect.anything(),
        );
        expect(
            dialog.getByText(
                'The reason field is required when status is suspended.',
            ),
        ).toBeInTheDocument();
    });

    it('warns when seats are almost full', () => {
        render(
            <TenantShow
                tab="overview"
                tenant={{ ...loneStar, members: 140 }}
                audit={[]}
                options={tenantOptions}
                support={noSupport}
            />,
        );

        expect(screen.getByText('140 of 142 used')).toBeInTheDocument();
        expect(
            screen.getByText('Almost full: 2 seats left'),
        ).toBeInTheDocument();
    });
});

describe('Support sign-in with consent (A4.1c)', () => {
    it('asks with a reason the owner will read', async () => {
        render(
            <TenantShow
                tab="overview"
                tenant={loneStar}
                audit={[]}
                options={tenantOptions}
                support={noSupport}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Sign in as support (with consent)',
            }),
        );
        const dialog = within(screen.getByRole('dialog'));
        expect(
            dialog.getByText(
                /Every owner of Lone Star Realty gets an email and decides/,
            ),
        ).toBeInTheDocument();
        await userEvent.type(
            dialog.getByLabelText('Reason the owner will read'),
            'Linda asked us to check her calendar sync',
        );
        await userEvent.click(
            dialog.getByRole('button', { name: 'Send request' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/tenants/3/support',
            { reason: 'Linda asked us to check her calendar sync' },
            expect.anything(),
        );
    });

    it('waits for the owner, then starts once approved', async () => {
        const pending = {
            can_ask: true,
            latest: {
                status: 'pending' as const,
                mine: true,
                requester: 'Ops Srini',
                decided_by: null,
                requested_at: null,
                ends_at: null,
            },
        };
        const { rerender } = render(
            <TenantShow
                tab="overview"
                tenant={loneStar}
                audit={[]}
                options={tenantOptions}
                support={pending}
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Sign in as support (with consent)',
            }),
        ).toBeDisabled();
        expect(
            screen.getByText('Support sign-in: waiting for the owner'),
        ).toBeInTheDocument();

        rerender(
            <I18nextProvider i18n={i18n}>
                <TenantShow
                    tab="overview"
                    tenant={loneStar}
                    audit={[]}
                    options={tenantOptions}
                    support={{
                        ...pending,
                        latest: {
                            ...pending.latest,
                            status: 'approved',
                            decided_by: 'Linda Park',
                        },
                    }}
                />
            </I18nextProvider>,
        );
        expect(
            screen.getByText('Support sign-in approved by Linda Park'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Start support session (60 minutes)',
            }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/admin/tenants/3/support/start',
        );
    });

    it('can’t ask a tenant with no owner', () => {
        render(
            <TenantShow
                tab="overview"
                tenant={loneStar}
                audit={[]}
                options={tenantOptions}
                support={{ can_ask: false, latest: null }}
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Sign in as support (with consent)',
            }),
        ).toBeDisabled();
        expect(
            screen.getByText(
                'No owner yet, so nobody can approve support sign-in.',
            ),
        ).toBeInTheDocument();
    });
});
