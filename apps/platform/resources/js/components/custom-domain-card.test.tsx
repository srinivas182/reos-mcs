import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CustomDomainCard, dnsName } from '@/components/custom-domain-card';
import { i18n } from '@/lib/i18n';
import type { TenantDomain } from '@/types';

const router = vi.hoisted(() => ({ post: vi.fn(), delete: vi.fn() }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router,
    usePage: () => ({ url: '/organisation/settings', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const pending: TenantDomain = {
    hostname: 'learn.lonestarrealty.com',
    status: 'pending',
    ssl: 'pending',
    live: false,
    records: [
        {
            type: 'CNAME',
            name: 'learn.lonestarrealty.com',
            value: 'tenants.reos.com',
            verified: false,
        },
        {
            type: 'TXT',
            name: '_reos-verify.learn.lonestarrealty.com',
            value: 'reos-4f1c9a',
            verified: false,
        },
    ],
    error: null,
    last_checked_at: null,
    verified_at: null,
};

beforeEach(() => vi.clearAllMocks());

describe('CustomDomainCard', () => {
    it('offers to add a domain and says where members sign in until then', () => {
        render(<CustomDomainCard domain={null} address="lone-star.reos.com" />);

        expect(screen.getByLabelText('Your app address')).toHaveAttribute(
            'placeholder',
            'learn.yourbrokerage.com',
        );
        expect(
            screen.getByRole('button', { name: 'Add domain' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Until then, your members sign in at lone-star.reos.com.',
            ),
        ).toBeInTheDocument();
    });

    it('lists the records to add with their status, and checks them on Verify DNS', async () => {
        render(
            <CustomDomainCard domain={pending} address="lone-star.reos.com" />,
        );

        expect(screen.getByText('tenants.reos.com')).toBeInTheDocument();
        expect(screen.getAllByText('Waiting')).toHaveLength(2);
        expect(
            screen.getByRole('button', { name: 'Copy TXT value' }),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Verify DNS' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/organisation/domain/verify',
            {},
            expect.anything(),
        );
    });

    it('shows a live domain as verified, and removes it only after confirming', async () => {
        const live: TenantDomain = {
            ...pending,
            status: 'active',
            ssl: 'active',
            live: true,
            records: pending.records.map((record) => ({
                ...record,
                verified: true,
            })),
        };
        render(<CustomDomainCard domain={live} address="lone-star.reos.com" />);

        expect(screen.getAllByText('Verified')).toHaveLength(2);
        expect(
            screen.getByText(/SSL certificate issued automatically/),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove domain' }),
        );
        expect(router.delete).not.toHaveBeenCalled();
        await userEvent.click(screen.getByRole('button', { name: 'Remove' }));
        expect(router.delete).toHaveBeenCalledWith(
            '/organisation/domain',
            expect.anything(),
        );
    });
});

describe('dnsName', () => {
    it('gives the name relative to the managed domain', () => {
        expect(dnsName('learn.lonestarrealty.com')).toBe('learn');
        expect(dnsName('_reos-verify.learn.lonestarrealty.com')).toBe(
            '_reos-verify.learn',
        );
        expect(dnsName('lonestarrealty.com')).toBe('lonestarrealty.com');
    });
});
