import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SsoCard, SsoStatusNote } from '@/components/sso-card';
import { i18n } from '@/lib/i18n';
import type { SsoScreen } from '@/types';

const router = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router,
    usePage: () => ({ url: '/organisation/branding', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const base = 'https://texas-realtors.reos.com/sso/texas-realtors';
const empty: SsoScreen = {
    settings: null,
    service_provider: {
        entity_id: `${base}/metadata`,
        acs_url: `${base}/callback`,
        metadata_url: `${base}/metadata`,
        redirect_uri: `${base}/callback`,
    },
    members: 0,
    test_provider_available: true,
};

const saml = (status: 'draft' | 'tested' | 'on'): SsoScreen => ({
    ...empty,
    members: 3,
    settings: {
        protocol: 'saml',
        status,
        entity_id: 'https://login.texasrealtors.example/saml',
        sso_url: 'https://login.texasrealtors.example/saml/sso',
        metadata_url: 'https://login.texasrealtors.example/saml/metadata',
        has_secret: false,
        certificate_expires: '2030-01-31T00:00:00+00:00',
        test_provider: false,
        last_test:
            status === 'draft'
                ? null
                : {
                      at: new Date().toISOString(),
                      passed: true,
                      detail: 'karen.webb@texasrealtors.example (Karen Webb)',
                  },
        enabled_at: null,
    },
});

beforeEach(() => vi.clearAllMocks());

describe('SsoCard', () => {
    it('starts with SAML: metadata URL or file, and the values to give the provider', () => {
        render(<SsoCard sso={empty} tenant="Texas REALTORS®" />);

        expect(screen.getByText('Off')).toBeInTheDocument();
        expect(screen.getByLabelText('Metadata URL')).toBeInTheDocument();
        expect(
            screen.getByLabelText('Or upload the metadata file'),
        ).toBeInTheDocument();
        expect(screen.getByText(`${base}/callback`)).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Test sign-in' }),
        ).not.toBeInTheDocument();
    });

    it('switches to OpenID Connect: issuer, client and the redirect URI', async () => {
        render(<SsoCard sso={empty} tenant="Texas REALTORS®" />);

        await userEvent.click(screen.getByLabelText('OpenID Connect'));

        expect(screen.getByLabelText('Issuer URL')).toBeInTheDocument();
        expect(screen.getByLabelText('Client ID')).toBeInTheDocument();
        expect(screen.getByLabelText('Client secret')).toHaveAttribute(
            'type',
            'password',
        );
        expect(
            screen.getByRole('button', { name: 'Copy Redirect URI' }),
        ).toBeInTheDocument();
    });

    it('offers the REOS test identity provider outside production', async () => {
        render(<SsoCard sso={empty} tenant="Texas REALTORS®" />);

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Use the REOS test identity provider',
            }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/organisation/sso/test-provider',
            { protocol: 'saml' },
            expect.anything(),
        );
    });

    it('asks for a test before it can be turned on, then turns it on', async () => {
        const { rerender } = render(
            <SsoCard sso={saml('draft')} tenant="Texas REALTORS®" />,
        );

        expect(screen.getByText('Not tested')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Turn on for members' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Test sign-in' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/organisation/sso/test',
            {},
            expect.anything(),
        );

        rerender(
            <I18nextProvider i18n={i18n}>
                <SsoCard sso={saml('tested')} tenant="Texas REALTORS®" />
            </I18nextProvider>,
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'karen.webb@texasrealtors.example',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Turn on for members' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/organisation/sso/enable',
            {},
            expect.anything(),
        );
    });

    it('shows when it is on, with a way to turn it off and the people using it', () => {
        render(<SsoCard sso={saml('on')} tenant="Texas REALTORS®" />);

        expect(screen.getByText('On')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Turn off' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('3 people have signed in with single sign-on.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('https://login.texasrealtors.example/saml'),
        ).toBeInTheDocument();
    });
});

describe('SsoStatusNote', () => {
    it('says single sign-on is on, as on journey screen 57', () => {
        render(<SsoStatusNote sso={saml('on')} tenant="Texas REALTORS®" />);

        expect(
            screen.getByText(
                "Single sign-on with Texas REALTORS®'s member login is on.",
            ),
        ).toBeInTheDocument();
    });
});
