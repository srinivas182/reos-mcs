import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import Branding from '@/pages/organisation/branding';

const router = vi.hoisted(() => ({
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/organisation/branding', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const props = {
    tenant: {
        id: 5,
        name: 'Texas REALTORS®',
        type: 'association' as const,
        address: 'texas-realtors.reos.com',
        parent: null,
    },
    brand: {
        organisation_id: 5,
        type: 'association' as const,
        name: 'Texas REALTORS®',
        app_label: 'Texas REALTORS® Learning',
        logo_url: null,
        primary: '#1E3A8A',
        palette: { action: '#1E3A8A', pressed: '#193071', tint: '#E9EBF3' },
        email_from_name: null,
        reply_to: null,
    },
    inherited: null,
    domain: null,
    sso: {
        settings: null,
        service_provider: {
            entity_id:
                'https://texas-realtors.reos.com/sso/texas-realtors/metadata',
            acs_url:
                'https://texas-realtors.reos.com/sso/texas-realtors/callback',
            metadata_url:
                'https://texas-realtors.reos.com/sso/texas-realtors/metadata',
            redirect_uri:
                'https://texas-realtors.reos.com/sso/texas-realtors/callback',
        },
        members: 0,
        test_provider_available: false,
    },
};

beforeEach(() => vi.clearAllMocks());

describe('Branding and domain (screen 57)', () => {
    it('shows the brand card, who sees it, the address and a preview', () => {
        render(<Branding {...props} />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Branding and domain',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Association brand + their brokerage brand'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Brokerage brand wins inside brokerage areas'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Your app address')).toHaveAttribute(
            'placeholder',
            'learn.yourbrokerage.com',
        );
        expect(
            screen.getByText(
                'Until then, your members sign in at texas-realtors.reos.com.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Texas REALTORS® · powered by REOS'),
        ).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent(
            'Readable: 10.4:1',
        );
        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    });

    it('warns about a hard-to-read colour, offers a darker one, and saves only changes', async () => {
        render(<Branding {...props} />);
        const input = screen.getByLabelText('Primary color', {
            selector: 'input#brand-primary',
        });

        await userEvent.clear(input);
        await userEvent.type(input, '#7FB3FF');
        expect(screen.getByRole('status')).toHaveTextContent(
            /Hard to read: 2\.1:1/,
        );
        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();

        await userEvent.click(screen.getByRole('button', { name: /^Use #/ }));
        expect(screen.getByRole('status')).toHaveTextContent(/Readable/);
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        const [url, data] = router.put.mock.calls[0]!;
        expect(url).toBe('/organisation/branding');
        expect(Object.keys(data as object)).toEqual(['primary']);
    });
});
