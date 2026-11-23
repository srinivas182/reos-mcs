import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import PackagesPage from '@/pages/public/packages';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { visit: vi.fn() },
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const packages = [
    {
        id: 3,
        product: 'Texas Pre-licence Complete',
        kind: 'package',
        description: 'Qualifying courses and exam prep',
        includes: ['180 hours of qualifying courses', 'Pass Promise'],
        interval: 'once' as const,
        amount: 49900,
        charge: null,
        sponsor: null,
        discounts: [{ sponsor: 'Texas Realty School', amount: 10000 }],
    },
];

describe('packages on reos.com (A8.3b)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('shows each package with what it includes, sponsor discounts, and Buy', () => {
        render(
            <PackagesPage
                state={{ code: 'TX', name: 'Texas' }}
                states={[{ code: 'TX', name: 'Texas' }]}
                packages={packages}
                signedIn={false}
            />,
        );

        expect(
            screen.getByRole('heading', {
                name: 'Packages for Texas candidates',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('$499')).toBeInTheDocument();
        expect(
            screen.getByText('Texas Realty School students: $100 off'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'Buy Texas Pre-licence Complete',
            }),
        ).toHaveAttribute('href', '/checkout/3');
    });

    it('checks what a code covers, then sends the person to use it', async () => {
        const user = userEvent.setup();
        fetchMock.mockResolvedValue(
            new Response(
                JSON.stringify({
                    data: {
                        code: 'TEX-7K2P',
                        sponsor: 'Texas Realty School',
                        product: 'Texas Pre-licence Complete',
                        state: 'TX',
                        months: 12,
                        expires_at: null,
                    },
                }),
                { status: 200 },
            ),
        );
        render(
            <PackagesPage
                state={{ code: 'TX', name: 'Texas' }}
                states={[{ code: 'TX', name: 'Texas' }]}
                packages={packages}
                signedIn={false}
            />,
        );

        await user.type(screen.getByLabelText('Code'), 'tex-7k2p');
        await user.click(screen.getByRole('button', { name: 'Check code' }));

        expect(
            await screen.findByText(
                'Texas Realty School covers Texas Pre-licence Complete.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Sign in to use this code' }),
        ).toHaveAttribute('href', '/settings/billing?code=TEX-7K2P');
    });
});
